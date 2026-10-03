<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Services;

use App\Core\Support\Contracts\StockBatch;
use App\Core\Support\Contracts\StockService;
use App\Modules\Inventory\Application\Actions\IssueStockAction;
use App\Modules\Inventory\Application\Actions\RecordStockMovementAction;
use App\Modules\Inventory\Domain\Enums\MovementType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockLevelModel;
use Illuminate\Support\Facades\DB;

final class StockServiceAdapter implements StockService
{
    public function __construct(
        private readonly IssueStockAction $issueStock,
        private readonly RecordStockMovementAction $movements,
    ) {}

    public function availableQuantity(int $inventoryItemId, ?int $warehouseId = null): float
    {
        return (float) StockLevelModel::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->sum('quantity');
    }

    /**
     * @return array<int, StockBatch>
     */
    public function issuableBatches(int $inventoryItemId, int $warehouseId): array
    {
        return InventoryBatchModel::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->where('warehouse_id', $warehouseId)
            ->available()
            ->notExpired()
            ->fefo()
            ->get()
            ->map(fn (InventoryBatchModel $batch) => new StockBatch(
                id: (int) $batch->getKey(),
                batchNumber: (string) $batch->batch_number,
                expiryDate: $batch->expiry_date?->toDateString(),
                quantity: (float) $batch->quantity,
                unitCost: (float) $batch->unit_cost,
            ))
            ->all();
    }

    /**
     * @return array<int, StockBatch>
     */
    public function issue(
        int $inventoryItemId,
        int $warehouseId,
        float $quantity,
        string $movementType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $batchId = null,
        ?string $notes = null,
    ): array {
        return $this->issueStock->execute(
            inventoryItemId: $inventoryItemId,
            warehouseId: $warehouseId,
            quantity: $quantity,
            type: MovementType::from($movementType),
            referenceType: $referenceType,
            referenceId: $referenceId,
            batchId: $batchId,
            notes: $notes,
        );
    }

    public function receiveBack(
        int $inventoryItemId,
        int $warehouseId,
        float $quantity,
        ?int $batchId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
    ): void {
        DB::transaction(function () use (
            $inventoryItemId,
            $warehouseId,
            $quantity,
            $batchId,
            $referenceType,
            $referenceId,
            $notes,
        ): void {
            if ($batchId !== null) {
                $batch = InventoryBatchModel::query()->lockForUpdate()->find($batchId);

                $batch?->forceFill(['quantity' => (float) $batch->quantity + $quantity])->save();
            }

            $this->movements->execute(
                inventoryItemId: $inventoryItemId,
                warehouseId: $warehouseId,
                signedQuantity: $quantity,
                type: MovementType::Return,
                batchId: $batchId,
                referenceType: $referenceType,
                referenceId: $referenceId,
                notes: $notes,
            );
        });
    }
}
