<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\StockBatch;
use App\Modules\Inventory\Domain\Enums\MovementType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use Illuminate\Support\Facades\DB;

/**
 * Moves stock between warehouses, most often main store to pharmacy. Batch
 * identity and expiry follow the goods, which is what keeps traceability
 * intact after a transfer.
 */
final class TransferStockAction
{
    public function __construct(
        private readonly IssueStockAction $issue,
        private readonly RecordStockMovementAction $movements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, StockBatch>
     */
    public function execute(array $data): array
    {
        $itemId = (int) $data['inventory_item_id'];
        $fromWarehouseId = (int) $data['from_warehouse_id'];
        $toWarehouseId = (int) $data['to_warehouse_id'];
        $quantity = (float) $data['quantity'];

        if ($fromWarehouseId === $toWarehouseId) {
            throw BusinessRuleException::make('مستودع المصدر والوجهة يجب أن يكونا مختلفين.');
        }

        return DB::transaction(function () use ($itemId, $fromWarehouseId, $toWarehouseId, $quantity, $data): array {
            $drawn = $this->issue->execute(
                inventoryItemId: $itemId,
                warehouseId: $fromWarehouseId,
                quantity: $quantity,
                type: MovementType::TransferOut,
                referenceType: 'warehouse_transfer',
                referenceId: $toWarehouseId,
                batchId: isset($data['batch_id']) ? (int) $data['batch_id'] : null,
                notes: $data['notes'] ?? null,
            );

            foreach ($drawn as $batch) {
                $destination = InventoryBatchModel::query()->firstOrNew([
                    'inventory_item_id' => $itemId,
                    'warehouse_id' => $toWarehouseId,
                    'batch_number' => $batch->batchNumber,
                ]);

                $destination->fill([
                    'expiry_date' => $batch->expiryDate,
                    'unit_cost' => $batch->unitCost,
                    'quantity' => (float) ($destination->quantity ?? 0) + $batch->quantity,
                ])->save();

                $this->movements->execute(
                    inventoryItemId: $itemId,
                    warehouseId: $toWarehouseId,
                    signedQuantity: $batch->quantity,
                    type: MovementType::TransferIn,
                    batchId: (int) $destination->getKey(),
                    referenceType: 'warehouse_transfer',
                    referenceId: $fromWarehouseId,
                    notes: $data['notes'] ?? null,
                );
            }

            return $drawn;
        });
    }
}
