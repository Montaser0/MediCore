<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Billing\Infrastructure\Persistence\Models\ServiceModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use App\Modules\Orders\Domain\Enums\OrderItemStatus;
use App\Modules\Orders\Domain\Enums\OrderType;
use App\Modules\Orders\Domain\Events\OrderCompleted;

/**
 * Procedure, consultation, supply and other orders are billed when the
 * order is completed. Laboratory, radiology and pharmacy are billed later,
 * when the result, report or dispense actually happens.
 */
final class ChargeCompletedServiceOrder
{
    public function __construct(private readonly CreateChargeAction $createCharge) {}

    public function handle(OrderCompleted $event): void
    {
        if (! config('hms.billing.auto_charge', true)) {
            return;
        }

        $order = $event->order;

        if (in_array($order->type, [OrderType::Laboratory, OrderType::Radiology, OrderType::Pharmacy], true)) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->status === OrderItemStatus::Cancelled) {
                continue;
            }

            $quantity = (float) $item->quantity;

            if ($quantity <= 0) {
                continue;
            }

            $price = $this->priceFor($item->service_type, $item->service_id);

            if ($price <= 0) {
                continue;
            }

            $this->createCharge->execute([
                'encounter_id' => (int) $order->encounter_id,
                'description' => $item->name,
                'quantity' => $quantity,
                'unit_price' => $price,
                'source_type' => ChargeSource::OrderItem->value,
                'source_id' => $item->getKey(),
            ]);
        }
    }

    private function priceFor(string $serviceType, mixed $serviceId): float
    {
        if ($serviceId === null) {
            return 0.0;
        }

        if ($serviceType === 'service') {
            return (float) (ServiceModel::query()->find($serviceId)?->price ?? 0);
        }

        if ($serviceType === 'inventory_item') {
            return (float) (InventoryBatchModel::query()
                ->where('inventory_item_id', $serviceId)
                ->where('unit_cost', '>', 0)
                ->orderByDesc('id')
                ->value('unit_cost') ?? 0);
        }

        return 0.0;
    }
}
