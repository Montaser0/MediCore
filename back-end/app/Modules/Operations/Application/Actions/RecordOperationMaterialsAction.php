<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\StockBatch;
use App\Core\Support\Contracts\StockService;
use App\Modules\Inventory\Domain\Enums\MovementType;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel;
use Illuminate\Support\Facades\DB;

/**
 * Records what was consumed in theatre and takes it out of the store in the
 * same breath, so the shelf count and the operative record never disagree.
 */
final class RecordOperationMaterialsAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly CurrentUser $currentUser,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $materials
     */
    public function execute(OperationModel $operation, int $warehouseId, array $materials): OperationModel
    {
        if (! $operation->status->isOpen()) {
            throw BusinessRuleException::make(
                "لا يمكن تسجيل مستهلكات والعملية {$operation->operation_number} حالتها «{$operation->status->label()}».",
            );
        }

        return DB::transaction(function () use ($operation, $warehouseId, $materials): OperationModel {
            foreach ($materials as $material) {
                $itemId = (int) $material['inventory_item_id'];
                $quantity = (float) $material['quantity'];

                $batches = $this->stock->issue(
                    inventoryItemId: $itemId,
                    warehouseId: $warehouseId,
                    quantity: $quantity,
                    movementType: MovementType::Sale->value,
                    referenceType: 'operation',
                    referenceId: $operation->getKey(),
                    notes: "استخدم في العملية {$operation->operation_number}",
                );

                $operation->materials()->create([
                    'inventory_item_id' => $itemId,
                    'quantity' => $quantity,
                    // A single line may draw from several batches at different
                    // costs, so the recorded cost is the weighted average.
                    'unit_cost' => $this->weightedUnitCost($batches),
                    'recorded_by' => $this->currentUser->id(),
                ]);
            }

            return $operation->fresh('materials.item');
        });
    }

    /**
     * @param  array<int, StockBatch>  $batches
     */
    private function weightedUnitCost(array $batches): float
    {
        $drawn = array_sum(array_map(static fn (StockBatch $batch): float => $batch->quantity, $batches));

        if ($drawn <= 0) {
            return 0.0;
        }

        $cost = array_sum(array_map(
            static fn (StockBatch $batch): float => $batch->quantity * $batch->unitCost,
            $batches,
        ));

        return round($cost / $drawn, 2);
    }
}
