<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Application\Actions;

use App\Core\Support\Contracts\OrderFulfiller;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use App\Modules\Radiology\Application\Services\RadiologyInjectionPrice;
use App\Modules\Radiology\Domain\Enums\RadiologyItemStatus;
use App\Modules\Radiology\Domain\Events\RadiologyOrderCreated;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyServiceModel;

/**
 * Radiology's side of a `radiology` order, called by the order engine inside
 * the creation transaction.
 */
final class CreateRadiologyOrderAction implements OrderFulfiller
{
    public function __construct(private readonly RadiologyInjectionPrice $injectionPrice) {}

    public function orderType(): string
    {
        return 'radiology';
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function fulfil(int $orderId, array $items): void
    {
        $order = OrderModel::query()->find($orderId);

        $withInjection = collect($items)->contains(
            fn (array $item): bool => filter_var($item['with_injection'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );

        $radiologyOrder = RadiologyOrderModel::query()->create([
            'order_id' => $orderId,
            'clinical_indication' => $order?->clinical_notes,
            'injection_price' => $withInjection ? $this->injectionPrice->amount() : 0,
        ]);

        foreach ($items as $item) {
            $service = $this->resolveService($item);

            if ($service === null) {
                continue;
            }

            RadiologyOrderItemModel::query()->create([
                'radiology_order_id' => $radiologyOrder->getKey(),
                'radiology_service_id' => $service->getKey(),
                'order_item_id' => $item['id'] ?? null,
                'with_injection' => filter_var($item['with_injection'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'status' => RadiologyItemStatus::Pending->value,
            ]);
        }

        RadiologyOrderCreated::dispatch($radiologyOrder->load('items.service'));
    }

    public function cancel(int $orderId, ?string $reason = null): void
    {
        $radiologyOrder = RadiologyOrderModel::query()->where('order_id', $orderId)->first();

        if ($radiologyOrder === null) {
            return;
        }

        $radiologyOrder->items()->open()->update(['status' => RadiologyItemStatus::Cancelled->value]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveService(array $item): ?RadiologyServiceModel
    {
        if (! empty($item['service_id'])) {
            $service = RadiologyServiceModel::query()->find($item['service_id']);

            if ($service !== null) {
                return $service;
            }
        }

        $name = (string) ($item['name'] ?? '');

        if ($name === '') {
            return null;
        }

        return RadiologyServiceModel::query()
            ->where('code', $name)
            ->orWhere('name', $name)
            ->first();
    }
}
