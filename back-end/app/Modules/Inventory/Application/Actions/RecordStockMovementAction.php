<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Modules\Inventory\Domain\Enums\MovementType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockLevelModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of stock quantities. Everything that changes stock goes
 * through here so the `stock_levels` row and the movement ledger can never
 * disagree.
 *
 * Callers are expected to already be inside a transaction; the row lock keeps
 * two simultaneous dispenses from reading the same balance.
 */
final class RecordStockMovementAction
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    public function execute(
        int $inventoryItemId,
        int $warehouseId,
        float $signedQuantity,
        MovementType $type,
        ?int $batchId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
    ): StockMovementModel {
        return DB::transaction(function () use (
            $inventoryItemId,
            $warehouseId,
            $signedQuantity,
            $type,
            $batchId,
            $referenceType,
            $referenceId,
            $notes,
        ): StockMovementModel {
            $level = $this->lockedLevel($inventoryItemId, $warehouseId);

            $balance = (float) $level->quantity + $signedQuantity;

            $level->forceFill(['quantity' => $balance])->save();

            return StockMovementModel::query()->create([
                'inventory_item_id' => $inventoryItemId,
                'warehouse_id' => $warehouseId,
                'batch_id' => $batchId,
                'type' => $type->value,
                'quantity' => $signedQuantity,
                'balance_after' => $balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'performed_by' => $this->currentUser->id(),
                'notes' => $notes,
                'created_at' => now(),
            ]);
        });
    }

    private function lockedLevel(int $inventoryItemId, int $warehouseId): StockLevelModel
    {
        StockLevelModel::query()->firstOrCreate(
            ['inventory_item_id' => $inventoryItemId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved_quantity' => 0],
        );

        return StockLevelModel::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
