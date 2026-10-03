<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Queries;

use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockLevelModel;
use Illuminate\Support\Facades\DB;

/**
 * The two warnings the warehouse screen exists to show: what is running out
 * and what is about to expire (PRD: low stock and expiry alerts).
 */
final class StockAlertsQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(?int $warehouseId = null, ?int $expiryDays = null): array
    {
        $expiryDays ??= (int) config('hms.inventory.expiry_alert_days', 90);

        return [
            'low_stock' => $this->lowStock($warehouseId),
            'expiring_soon' => $this->expiringSoon($warehouseId, $expiryDays),
            'expired' => $this->expired($warehouseId),
            'expiry_window_days' => $expiryDays,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lowStock(?int $warehouseId): array
    {
        return StockLevelModel::query()
            ->with(['item', 'warehouse'])
            ->join('inventory_items', 'inventory_items.id', '=', 'stock_levels.inventory_item_id')
            ->where('inventory_items.is_active', true)
            ->whereColumn('stock_levels.quantity', '<=', 'inventory_items.minimum_stock')
            ->when($warehouseId !== null, fn ($query) => $query->where('stock_levels.warehouse_id', $warehouseId))
            ->select('stock_levels.*')
            ->orderBy('stock_levels.quantity')
            ->limit(200)
            ->get()
            ->map(fn (StockLevelModel $level) => [
                'inventory_item_id' => $level->inventory_item_id,
                'item' => $level->item?->name,
                'sku' => $level->item?->sku,
                'unit' => $level->item?->unit,
                'warehouse' => $level->warehouse?->name,
                'quantity' => $level->quantity,
                'minimum_stock' => $level->item?->minimum_stock,
                'reorder_quantity' => $level->item?->reorder_quantity,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expiringSoon(?int $warehouseId, int $days): array
    {
        return InventoryBatchModel::query()
            ->with(['item', 'warehouse'])
            ->available()
            ->expiringWithin($days)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('expiry_date')
            ->limit(200)
            ->get()
            ->map(fn (InventoryBatchModel $batch) => [
                'batch_id' => $batch->getKey(),
                'item' => $batch->item?->name,
                'batch_number' => $batch->batch_number,
                'warehouse' => $batch->warehouse?->name,
                'quantity' => $batch->quantity,
                'expiry_date' => $batch->expiry_date?->toDateString(),
                'days_remaining' => $batch->daysUntilExpiry(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expired(?int $warehouseId): array
    {
        return InventoryBatchModel::query()
            ->with(['item', 'warehouse'])
            ->available()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('expiry_date')
            ->limit(200)
            ->get()
            ->map(fn (InventoryBatchModel $batch) => [
                'batch_id' => $batch->getKey(),
                'item' => $batch->item?->name,
                'batch_number' => $batch->batch_number,
                'warehouse' => $batch->warehouse?->name,
                'quantity' => $batch->quantity,
                'expiry_date' => $batch->expiry_date?->toDateString(),
            ])
            ->all();
    }

    /**
     * Totals for the dashboard tiles.
     *
     * @return array<string, mixed>
     */
    public function counts(?int $warehouseId = null): array
    {
        $expiryDays = (int) config('hms.inventory.expiry_alert_days', 90);

        return [
            'low_stock' => DB::table('stock_levels')
                ->join('inventory_items', 'inventory_items.id', '=', 'stock_levels.inventory_item_id')
                ->where('inventory_items.is_active', true)
                ->whereColumn('stock_levels.quantity', '<=', 'inventory_items.minimum_stock')
                ->when($warehouseId !== null, fn ($query) => $query->where('stock_levels.warehouse_id', $warehouseId))
                ->count(),
            'expiring_soon' => InventoryBatchModel::query()
                ->available()
                ->expiringWithin($expiryDays)
                ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
                ->count(),
            'expired' => InventoryBatchModel::query()
                ->available()
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', now()->toDateString())
                ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
                ->count(),
        ];
    }
}
