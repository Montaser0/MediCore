<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Queries;

use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;

final class PharmacyAlertQuery
{
    /**
     * @return array{expiring: array<int, array<string, mixed>>, low_stock: array<int, array<string, mixed>>}
     */
    public function execute(int $hospitalId): array
    {
        return [
            'expiring' => $this->expiring($hospitalId),
            'low_stock' => $this->lowStock($hospitalId),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expiring(int $hospitalId): array
    {
        $warehouseIds = WarehouseModel::query()
            ->where('hospital_id', $hospitalId)
            ->pharmacy()
            ->pluck('id');

        if ($warehouseIds->isEmpty()) {
            return [];
        }

        $batches = InventoryBatchModel::query()
            ->with('item')
            ->available()
            ->expiringWithin(90)
            ->whereIn('warehouse_id', $warehouseIds)
            ->orderBy('expiry_date')
            ->limit(200)
            ->get();

        $medications = MedicationModel::query()
            ->where('hospital_id', $hospitalId)
            ->whereIn('inventory_item_id', $batches->pluck('inventory_item_id')->filter()->unique())
            ->get()
            ->keyBy('inventory_item_id');

        return $batches->map(function (InventoryBatchModel $batch) use ($medications): array {
            $medication = $medications->get($batch->inventory_item_id);

            return [
                'medication_id' => $medication?->id,
                'name' => $medication?->displayName() ?? $batch->item?->name ?? $batch->batch_number,
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date?->toDateString(),
                'days_left' => $batch->daysUntilExpiry(),
                'quantity' => $batch->quantity,
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lowStock(int $hospitalId): array
    {
        $rows = MedicationModel::query()
            ->with('inventoryItem.stockLevels')
            ->where('hospital_id', $hospitalId)
            ->active()
            ->where('reorder_level', '>', 0)
            ->orderBy('name')
            ->get();

        $short = [];
        foreach ($rows as $medication) {
            $onHand = $this->onHand($medication);
            if ($onHand > (float) $medication->reorder_level) {
                continue;
            }

            $short[] = [
                'medication_id' => $medication->id,
                'name' => $medication->displayName(),
                'on_hand' => $onHand,
                'reorder_level' => (float) $medication->reorder_level,
            ];
        }

        return $short;
    }

    private function onHand(MedicationModel $medication): float
    {
        if ($medication->inventory_item_id === null) {
            return round((float) $medication->quantity, 2);
        }

        $levels = $medication->inventoryItem?->relationLoaded('stockLevels')
            ? $medication->inventoryItem->stockLevels
            : null;

        if ($levels === null) {
            return round((float) ($medication->inventoryItem?->totalQuantity() ?? 0), 2);
        }

        return round((float) $levels->sum('quantity'), 2);
    }
};
