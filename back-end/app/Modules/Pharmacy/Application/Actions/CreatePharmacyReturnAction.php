<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\StockService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyReturnItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyReturnModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleModel;
use Illuminate\Support\Facades\DB;

final class CreatePharmacyReturnAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly CreatePharmacySaleAction $sales,
        private readonly StockService $stock,
    ) {}

    /**
     * @param  array<int, array{sale_item_id: int, quantity: float|int|string}>  $lines
     */
    public function execute(int $saleId, array $lines, ?string $reason = null): PharmacyReturnModel
    {
        if ($lines === []) {
            throw BusinessRuleException::make('حدد صنفاً للمرتجع');
        }

        $hospitalId = $this->sales->hospitalId();

        return DB::transaction(function () use ($saleId, $lines, $reason, $hospitalId): PharmacyReturnModel {
            $sale = PharmacySaleModel::query()
                ->whereKey($saleId)
                ->where('hospital_id', $hospitalId)
                ->lockForUpdate()
                ->first();

            if ($sale === null) {
                throw BusinessRuleException::make('الفاتورة غير موجودة');
            }

            $document = PharmacyReturnModel::query()->create([
                'hospital_id' => $hospitalId,
                'pharmacy_sale_id' => $sale->getKey(),
                'user_id' => $this->currentUser->id(),
                'return_number' => 'PENDING',
                'total' => 0,
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                'returned_at' => now(),
            ]);
            $document->return_number = 'RET-'.now()->format('Y').'-'.str_pad((string) $document->getKey(), 5, '0', STR_PAD_LEFT);

            $ratio = (float) $sale->subtotal > 0
                ? round((float) $sale->total / (float) $sale->subtotal, 6)
                : 1.0;
            $total = 0.0;
            $lastItem = null;
            foreach ($lines as $line) {
                $quantity = round((float) $line['quantity'], 2);
                if ($quantity <= 0) {
                    continue;
                }

                $item = PharmacySaleItemModel::query()
                    ->whereKey((int) $line['sale_item_id'])
                    ->where('pharmacy_sale_id', $sale->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($item === null) {
                    throw BusinessRuleException::make('الصنف ليس من هذه الفاتورة');
                }

                $already = round((float) PharmacyReturnItemModel::query()
                    ->where('pharmacy_sale_item_id', $item->getKey())
                    ->sum('quantity'), 2);
                $returnable = round((float) $item->quantity - $already, 2);
                if ($quantity > $returnable) {
                    throw BusinessRuleException::make('الكمية المرتجعة أكبر من المباع: '.$item->name);
                }

                if ($item->medication_id !== null) {
                    $medication = MedicationModel::query()
                        ->whereKey($item->medication_id)
                        ->where('hospital_id', $hospitalId)
                        ->lockForUpdate()
                        ->first();

                    if ($medication !== null && $medication->inventory_item_id !== null) {
                        $this->restoreStock($medication, $hospitalId, $quantity, (int) $item->getKey());
                    } elseif ($medication !== null) {
                        $medication->quantity = round((float) $medication->quantity + $quantity, 2);
                        $medication->save();
                    }
                }

                $lineTotal = round($quantity * (float) $item->unit_price * $ratio, 2);
                $total += $lineTotal;

                $lastItem = PharmacyReturnItemModel::query()->create([
                    'pharmacy_return_id' => $document->getKey(),
                    'pharmacy_sale_item_id' => $item->getKey(),
                    'medication_id' => $item->medication_id,
                    'name' => $item->name,
                    'barcode' => $item->barcode,
                    'quantity' => $quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $lineTotal,
                ]);
            }

            if ($total <= 0 || $lastItem === null) {
                throw BusinessRuleException::make('حدد صنفاً للمرتجع');
            }

            $already = round((float) PharmacyReturnModel::query()
                ->where('pharmacy_sale_id', $sale->getKey())
                ->whereKeyNot($document->getKey())
                ->sum('total'), 2);
            $remaining = round((float) $sale->total - $already, 2);
            if ($total > $remaining) {
                $adjusted = round((float) $lastItem->line_total - ($total - $remaining), 2);
                if ($adjusted < 0) {
                    throw BusinessRuleException::make('المبلغ المرتجع أكبر من صافي الفاتورة');
                }
                $lastItem->line_total = $adjusted;
                $lastItem->save();
                $total = $remaining;
            }

            $document->total = round($total, 2);
            $fromDebt = round(min($total, max((float) $sale->due_amount, 0)), 2);
            $sale->due_amount = round((float) $sale->due_amount - $fromDebt, 2);
            $sale->save();
            $document->cash_refund = round($total - $fromDebt, 2);
            $document->save();

            return $document->load('items');
        });
    }

    private function restoreStock(MedicationModel $medication, int $hospitalId, float $quantity, int $saleItemId): void
    {
        $soldFrom = StockMovementModel::query()
            ->where('reference_type', 'pharmacy_sale_item')
            ->where('reference_id', $saleItemId)
            ->where('type', 'sale')
            ->value('warehouse_id');
        $warehouseId = $soldFrom !== null
            ? (int) $soldFrom
            : $this->sales->pharmacyWarehouseId($hospitalId, (int) $medication->inventory_item_id);
        $batch = InventoryBatchModel::query()
            ->where('inventory_item_id', $medication->inventory_item_id)
            ->where('warehouse_id', $warehouseId)
            ->where(function ($query): void {
                $query->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', now()->toDateString());
            })
            ->orderByDesc('id')
            ->first();

        if (! $batch instanceof InventoryBatchModel) {
            $batch = InventoryBatchModel::query()->create([
                'inventory_item_id' => $medication->inventory_item_id,
                'warehouse_id' => $warehouseId,
                'batch_number' => 'RETURN',
                'expiry_date' => now()->addYear()->toDateString(),
                'quantity' => 0,
                'unit_cost' => 0,
            ]);
        }

        $this->stock->receiveBack(
            inventoryItemId: (int) $medication->inventory_item_id,
            warehouseId: $warehouseId,
            quantity: $quantity,
            batchId: (int) $batch->getKey(),
            referenceType: 'pharmacy_sale_item',
            referenceId: $saleItemId,
            notes: 'مرتجع بيع',
        );
    }
}
