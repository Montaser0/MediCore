<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Inventory\Domain\Enums\MovementType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

/**
 * Stock-take corrections and expiry write-offs. A reason is mandatory: an
 * adjustment is the one place stock changes without a clinical or purchasing
 * event behind it, so the audit trail has to explain itself.
 */
final class AdjustStockAction
{
    public function __construct(private readonly RecordStockMovementAction $movements) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): StockMovementModel
    {
        $batch = InventoryBatchModel::query()->findOrFail((int) $data['batch_id']);
        $delta = (float) $data['quantity'];
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            throw BusinessRuleException::make('التسوية تحتاج سبباً.');
        }

        if ($delta === 0.0) {
            throw BusinessRuleException::make('تسوية بصفر لا تغيّر الرصيد.');
        }

        if ((float) $batch->quantity + $delta < 0) {
            throw BusinessRuleException::make(
                "رصيد الدفعة {$batch->batch_number} هو {$batch->quantity}، وهذه التسوية تجعله سالباً.",
            );
        }

        $type = isset($data['type'])
            ? MovementType::from((string) $data['type'])
            : MovementType::Adjustment;

        return DB::transaction(function () use ($batch, $delta, $type, $reason): StockMovementModel {
            $batch->forceFill(['quantity' => (float) $batch->quantity + $delta])->save();

            return $this->movements->execute(
                inventoryItemId: (int) $batch->inventory_item_id,
                warehouseId: (int) $batch->warehouse_id,
                signedQuantity: $delta,
                type: $type,
                batchId: (int) $batch->getKey(),
                referenceType: 'stock_adjustment',
                referenceId: null,
                notes: $reason,
            );
        });
    }

    /**
     * Writes off everything that has passed its expiry date, one movement per
     * batch so the ledger shows exactly what was destroyed.
     *
     * @return array<int, StockMovementModel>
     */
    public function writeOffExpired(?int $warehouseId = null): array
    {
        $batches = InventoryBatchModel::query()
            ->available()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->get();

        $written = [];

        foreach ($batches as $batch) {
            $quantity = (float) $batch->quantity;

            $written[] = DB::transaction(function () use ($batch, $quantity): StockMovementModel {
                $batch->forceFill(['quantity' => 0])->save();

                return $this->movements->execute(
                    inventoryItemId: (int) $batch->inventory_item_id,
                    warehouseId: (int) $batch->warehouse_id,
                    signedQuantity: -$quantity,
                    type: MovementType::Expired,
                    batchId: (int) $batch->getKey(),
                    referenceType: 'expiry_write_off',
                    referenceId: null,
                    notes: 'Expired on '.$batch->expiry_date?->toDateString(),
                );
            });
        }

        return $written;
    }
}
