<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Core\Support\Numbering\NumberGenerator;
use App\Core\Support\OrderFulfillerRegistry;
use App\Modules\Orders\Domain\Enums\OrderPriority;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Enums\OrderType;
use App\Modules\Orders\Domain\Events\OrderCreated;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use Illuminate\Support\Facades\DB;

/**
 * The one place an order is born. The doctor says what is needed; the engine
 * numbers it, records it and hands it to the department that will do the work.
 */
final class CreateOrderAction
{
    public function __construct(
        private readonly EncounterContext $encounters,
        private readonly NumberGenerator $numbers,
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
        private readonly NotificationDispatcher $notifications,
        private readonly OrderFulfillerRegistry $fulfillers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): OrderModel
    {
        $encounter = $this->encounters->requireOpen((int) $data['encounter_id']);

        $type = OrderType::from((string) $data['type']);
        $priority = OrderPriority::from((string) ($data['priority'] ?? OrderPriority::Routine->value));

        $items = $data['items'] ?? [];

        if ($items === []) {
            throw BusinessRuleException::make('الطلب يحتاج بنداً واحداً على الأقل.');
        }

        return DB::transaction(function () use ($encounter, $data, $type, $priority, $items): OrderModel {
            $order = OrderModel::query()->create([
                'hospital_id' => $encounter->hospitalId,
                'encounter_id' => $encounter->id,
                'order_number' => $this->numbers->orderNumber($encounter->hospitalId),
                'type' => $type->value,
                'status' => OrderStatus::Pending->value,
                'priority' => $priority->value,
                'ordered_by' => $this->currentUser->id(),
                'department_id' => $data['department_id'] ?? null,
                'clinical_notes' => $data['clinical_notes'] ?? null,
                'ordered_at' => now(),
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'service_type' => $item['service_type'] ?? $type->serviceType(),
                    'service_id' => $item['service_id'] ?? null,
                    'name' => (string) $item['name'],
                    'quantity' => $item['quantity'] ?? 1,
                    'instructions' => $item['instructions'] ?? null,
                ]);
            }

            $order->load('items');

            // The receiving department creates its own records inside the same
            // transaction, so an order and its lab/radiology rows appear together.
            $this->fulfillers->for($type->value)?->fulfil(
                $order->getKey(),
                $order->items->map(fn ($item) => $item->only([
                    'id', 'service_type', 'service_id', 'name', 'quantity', 'instructions',
                ]))->all(),
            );

            OrderCreated::dispatch($order);

            $this->timeline->record(
                patientId: $encounter->patientId,
                encounterId: $encounter->id,
                eventType: 'order.created',
                title: 'طلب '.$type->label(),
                description: $order->items->pluck('name')->implode(', '),
                departmentId: $order->department_id,
                payload: ['order_id' => $order->getKey(), 'priority' => $priority->value],
            );

            $this->announce($order, $type, $priority);

            return $order;
        });
    }

    /**
     * Tell the receiving department that work has arrived; urgent and stat
     * orders are what the PRD wants staff alerted about.
     */
    private function announce(OrderModel $order, OrderType $type, OrderPriority $priority): void
    {
        $title = $priority->requiresAlert()
            ? $priority->label().' — طلب '.$type->label()
            : 'طلب '.$type->label().' جديد';

        $message = $order->items->pluck('name')->implode('، ');

        if ($order->department_id !== null) {
            $this->notifications->toDepartment(
                departmentId: (int) $order->department_id,
                type: 'order.created',
                title: $title,
                message: $message,
                entityType: 'order',
                entityId: $order->getKey(),
                payload: ['priority' => $priority->value, 'order_type' => $type->value],
            );

            return;
        }

        $this->notifications->toPermissionHolders(
            permission: $this->permissionFor($type),
            type: 'order.created',
            title: $title,
            message: $message,
            entityType: 'order',
            entityId: $order->getKey(),
            payload: ['priority' => $priority->value, 'order_type' => $type->value],
        );
    }

    private function permissionFor(OrderType $type): string
    {
        return match ($type) {
            OrderType::Laboratory => 'laboratory.orders.view',
            OrderType::Radiology => 'radiology.orders.view',
            OrderType::Pharmacy => 'pharmacy.prescriptions.view',
            default => 'orders.view',
        };
    }
}
