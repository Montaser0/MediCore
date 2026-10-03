<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Actions;

use App\Core\Support\Contracts\OrderFulfiller;
use App\Modules\Laboratory\Domain\Enums\LabOrderItemStatus;
use App\Modules\Laboratory\Domain\Events\LabOrderCreated;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabTestModel;
use App\Modules\Orders\Domain\Enums\OrderItemStatus;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItemModel;

/**
 * The laboratory's side of a `laboratory` order. Invoked by the order engine
 * inside the creation transaction, so an order never exists without its lab
 * worklist rows.
 */
final class CreateLabOrderAction implements OrderFulfiller
{
    public function orderType(): string
    {
        return 'laboratory';
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function fulfil(int $orderId, array $items): void
    {
        $labOrder = LabOrderModel::query()->create(['order_id' => $orderId]);

        foreach ($items as $item) {
            $test = $this->resolveTest($item);

            if ($test === null) {
                continue;
            }

            LabOrderItemModel::query()->create([
                'lab_order_id' => $labOrder->getKey(),
                'lab_test_id' => $test->getKey(),
                'order_item_id' => $item['id'] ?? null,
                'status' => LabOrderItemStatus::Pending->value,
            ]);
        }

        LabOrderCreated::dispatch($labOrder->load('items.test'));
    }

    public function cancel(int $orderId, ?string $reason = null): void
    {
        $labOrder = LabOrderModel::query()->where('order_id', $orderId)->first();

        if ($labOrder === null) {
            return;
        }

        $labOrder->items()->open()->update(['status' => LabOrderItemStatus::Cancelled->value]);
    }

    /**
     * The doctor normally picks from the catalogue, but a free-typed test name
     * is matched on code or name so nothing is silently dropped.
     *
     * @param  array<string, mixed>  $item
     */
    private function resolveTest(array $item): ?LabTestModel
    {
        if (! empty($item['service_id'])) {
            $test = LabTestModel::query()->find($item['service_id']);

            if ($test !== null) {
                return $test;
            }
        }

        $name = (string) ($item['name'] ?? '');

        if ($name === '') {
            return null;
        }

        $test = LabTestModel::query()
            ->where('code', $name)
            ->orWhere('name', $name)
            ->first();

        if ($test === null && isset($item['id'])) {
            // The catalogue does not know this test; leave the order item
            // pending so the laboratory can triage it manually.
            OrderItemModel::query()
                ->whereKey($item['id'])
                ->update(['status' => OrderItemStatus::Pending->value]);
        }

        return $test;
    }
}
