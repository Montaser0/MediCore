<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Core\Support\Contracts\StockBatch;
use App\Modules\Inventory\Domain\Enums\MovementType;
use App\Modules\Inventory\Domain\Exceptions\ExpiredBatchException;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use Illuminate\Support\Facades\DB;

/**
 * Goods-out. Batches are drawn nearest-expiry-first unless the caller names
 * one, expired stock is refused outright, and the request fails rather than
 * letting a balance go negative.
 */
final class IssueStockAction
{
    public function __construct(private readonly RecordStockMovementAction $movements) {}

    /**
     * @return array<int, StockBatch>
     */
    public function execute(
        int $inventoryItemId,
        int $warehouseId,
        float $quantity,
        MovementType $type = MovementType::Dispense,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $batchId = null,
        ?string $notes = null,
    ): array {
        $item = InventoryItemModel::query()->findOrFail($inventoryItemId);

        if ($quantity <= 0) {
            throw InsufficientStockException::for((string) $item->name, $quantity, 0);
        }

        return DB::transaction(function () use (
            $item,
            $inventoryItemId,
            $warehouseId,
            $quantity,
            $type,
            $referenceType,
            $referenceId,
            $batchId,
            $notes,
        ): array {
            $batches = $this->lockBatches($inventoryItemId, $warehouseId, $batchId);

            $available = array_sum(array_map(static fn (InventoryBatchModel $batch) => (float) $batch->quantity, $batches));

            if ($available + 1e-6 < $quantity) {
                throw InsufficientStockException::for((string) $item->name, $quantity, $available);
            }

            $remaining = $quantity;
            $drawn = [];

            foreach ($batches as $batch) {
                if ($remaining <= 1e-6) {
                    break;
                }

                $take = min($remaining, (float) $batch->quantity);

                $batch->forceFill(['quantity' => (float) $batch->quantity - $take])->save();

                $this->movements->execute(
                    inventoryItemId: $inventoryItemId,
                    warehouseId: $warehouseId,
                    signedQuantity: -$take,
                    type: $type,
                    batchId: (int) $batch->getKey(),
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    notes: $notes,
                );

                $drawn[] = new StockBatch(
                    id: (int) $batch->getKey(),
                    batchNumber: (string) $batch->batch_number,
                    expiryDate: $batch->expiry_date?->toDateString(),
                    quantity: $take,
                    unitCost: (float) $batch->unit_cost,
                );

                $remaining -= $take;
            }

            return $drawn;
        });
    }

    /**
     * Batches are locked in issue order so two concurrent dispenses cannot
     * both claim the last box.
     *
     * @return array<int, InventoryBatchModel>
     */
    private function lockBatches(int $inventoryItemId, int $warehouseId, ?int $batchId): array
    {
        $query = InventoryBatchModel::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->where('warehouse_id', $warehouseId)
            ->available()
            ->when($batchId !== null, fn ($q) => $q->whereKey($batchId))
            ->fefo()
            ->lockForUpdate();

        $batches = $query->get();

        foreach ($batches as $batch) {
            if ($batch->isExpired()) {
                throw ExpiredBatchException::for(
                    (string) $batch->batch_number,
                    $batch->expiry_date?->toDateString(),
                );
            }
        }

        return $batches->all();
    }
}
