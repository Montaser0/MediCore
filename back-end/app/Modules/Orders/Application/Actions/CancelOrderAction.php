<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Actions;

use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Core\Support\OrderFulfillerRegistry;
use App\Modules\Orders\Domain\Enums\OrderItemStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Events\OrderCancelled;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use Illuminate\Support\Facades\DB;

final class CancelOrderAction
{
    public function __construct(
        private readonly ChangeOrderStatusAction $changeStatus,
        private readonly OrderFulfillerRegistry $fulfillers,
        private readonly EncounterContext $encounters,
        private readonly TimelineRecorder $timeline,
    ) {}

    public function execute(OrderModel $order, ?string $reason = null): OrderModel
    {
        return DB::transaction(function () use ($order, $reason): OrderModel {
            $order = $this->changeStatus->execute($order, OrderStatus::Cancelled);

            $order->forceFill(['cancellation_reason' => $reason])->save();

            $order->items()
                ->whereIn('status', [OrderItemStatus::Pending->value, OrderItemStatus::InProgress->value])
                ->update(['status' => OrderItemStatus::Cancelled->value]);

            // Let the department void its own records for this order.
            $this->fulfillers->for($order->type->value)?->cancel($order->getKey(), $reason);

            OrderCancelled::dispatch($order, $reason);

            $encounter = $this->encounters->snapshot((int) $order->encounter_id);

            $this->timeline->record(
                patientId: $encounter->patientId,
                encounterId: $encounter->id,
                eventType: 'order.cancelled',
                title: 'إلغاء طلب '.$order->type->label(),
                description: $reason,
                departmentId: $order->department_id,
                payload: ['order_id' => $order->getKey()],
            );

            return $order;
        });
    }
}
