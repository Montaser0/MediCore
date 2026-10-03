<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Inventory\Domain\Enums\MovementType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Goods-in: a delivery arrives, a batch is created or topped up, and the
 * movement is logged.
 */
final class ReceiveStockAction
{
    public function __construct(private readonly RecordStockMovementAction $movements) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): InventoryBatchModel
    {
        $item = InventoryItemModel::query()->findOrFail((int) $data['inventory_item_id']);
        $warehouseId = (int) $data['warehouse_id'];
        $quantity = (float) $data['quantity'];

        if ($quantity <= 0) {
            throw BusinessRuleException::make('الكمية المستلمة يجب أن تكون أكبر من صفر.');
        }

        $expiry = isset($data['expiry_date']) ? Carbon::parse((string) $data['expiry_date']) : null;

        if ($expiry !== null && $expiry->isPast()) {
            throw BusinessRuleException::make('لا يمكن استلام صنف منتهي الصلاحية.');
        }

        if ($item->item_type->tracksExpiry() && $expiry === null && $item->is_batch_tracked) {
            throw BusinessRuleException::make('الصنف '.$item->name.' يحتاج تاريخ صلاحية.');
        }

        return DB::transaction(function () use ($item, $warehouseId, $quantity, $expiry, $data): InventoryBatchModel {
            $batchNumber = (string) ($data['batch_number'] ?? 'NOBATCH');

            $batch = InventoryBatchModel::query()->firstOrNew([
                'inventory_item_id' => $item->getKey(),
                'warehouse_id' => $warehouseId,
                'batch_number' => $batchNumber,
            ]);

            $batch->fill([
                'expiry_date' => $expiry?->toDateString() ?? $batch->expiry_date,
                'unit_cost' => $data['unit_cost'] ?? $batch->unit_cost ?? 0,
                'quantity' => (float) ($batch->quantity ?? 0) + $quantity,
            ])->save();

            $this->movements->execute(
                inventoryItemId: (int) $item->getKey(),
                warehouseId: $warehouseId,
                signedQuantity: $quantity,
                type: MovementType::Purchase,
                batchId: (int) $batch->getKey(),
                referenceType: $data['reference_type'] ?? null,
                referenceId: isset($data['reference_id']) ? (int) $data['reference_id'] : null,
                notes: $data['notes'] ?? null,
            );

            return $batch->load(['item', 'warehouse']);
        });
    }
}
