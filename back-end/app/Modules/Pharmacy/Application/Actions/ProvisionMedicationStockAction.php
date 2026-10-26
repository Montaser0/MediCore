<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockLevelModel;
use App\Modules\Pharmacy\Application\Support\MedicationStockRules;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\DrugCatalogueItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use Illuminate\Support\Facades\DB;

/**
 * Turns a local medicine into an inventory item. Creates no quantity:
 * a balance appears only when a batch is received.
 */
final class ProvisionMedicationStockAction
{
    public function __construct(private readonly MedicationStockRules $rules) {}

    public function execute(
        MedicationModel $medication,
        int $hospitalId,
        ?string $unit = null,
        ?string $barcode = null,
        ?int $warehouseId = null,
    ): InventoryItemModel {
        return DB::transaction(function () use ($medication, $hospitalId, $unit, $barcode, $warehouseId): InventoryItemModel {
            $locked = MedicationModel::query()->whereKey($medication->getKey())->lockForUpdate()->firstOrFail();
            $this->rules->owned($locked, $hospitalId);

            if ($locked->hospital_id === null) {
                $locked->hospital_id = $hospitalId;
            }

            if (blank($locked->barcode) && $locked->catalogue_item_id !== null) {
                $fromCatalogue = DrugCatalogueItemModel::query()
                    ->whereKey($locked->catalogue_item_id)
                    ->value('barcode');
                if (filled($fromCatalogue)) {
                    $locked->barcode = $fromCatalogue;
                }
            }

            if ($locked->inventory_item_id !== null) {
                $existing = InventoryItemModel::query()->find($locked->inventory_item_id);
                if (! $existing instanceof InventoryItemModel) {
                    throw BusinessRuleException::make('صنف المخزون المرتبط بهذا الدواء غير موجود.');
                }

                $this->place($existing, $warehouseId, $hospitalId);
                $locked->save();

                return $existing;
            }

            $code = $this->itemBarcode($locked, $hospitalId, $barcode);
            $item = InventoryItemModel::query()->create([
                'name' => mb_substr($locked->displayName(), 0, 255),
                'sku' => 'MED-'.$hospitalId.'-'.$locked->getKey(),
                'barcode' => $code,
                'category' => $locked->category !== null ? mb_substr((string) $locked->category, 0, 150) : null,
                'unit' => $this->unit($unit),
                'item_type' => 'medicine',
                'is_batch_tracked' => true,
                'is_active' => true,
            ]);

            if ($code === null && filled($item->barcode)) {
                $item->barcode = null;
                $item->save();
            }

            if (filled($code)) {
                $locked->barcode = $code;
            }

            $locked->inventory_item_id = $item->getKey();
            $locked->save();
            $this->place($item, $warehouseId, $hospitalId);

            return $item;
        });
    }

    private function place(InventoryItemModel $item, ?int $warehouseId, int $hospitalId): void
    {
        if ($warehouseId === null) {
            return;
        }

        $this->rules->pharmacyWarehouse($warehouseId, $hospitalId);

        StockLevelModel::query()->firstOrCreate(
            [
                'inventory_item_id' => $item->getKey(),
                'warehouse_id' => $warehouseId,
            ],
            ['quantity' => 0, 'reserved_quantity' => 0],
        );
    }

    private function unit(?string $unit): string
    {
        $unit = trim((string) $unit);

        return $unit === '' ? 'علبة' : mb_substr($unit, 0, 50);
    }

    private function itemBarcode(MedicationModel $medication, int $hospitalId, ?string $requested): ?string
    {
        $requested = trim((string) $requested);
        $code = $requested !== '' ? $requested : trim((string) $medication->barcode);

        if ($code === '') {
            return null;
        }

        $this->guardMedicationBarcode($medication, $hospitalId, $code);

        if (InventoryItemModel::query()->where('barcode', $code)->exists()) {
            if ($requested !== '') {
                throw BusinessRuleException::make('هذا الباركود مستخدم لصنف مخزون آخر.');
            }

            return null;
        }

        if ($requested !== '') {
            $medication->barcode = $code;
        }

        return $code;
    }

    private function guardMedicationBarcode(MedicationModel $medication, int $hospitalId, string $code): void
    {
        $taken = MedicationModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('barcode', $code)
            ->whereKeyNot($medication->getKey())
            ->exists();

        if ($taken) {
            throw BusinessRuleException::make('هذا الباركود مستخدم لدواء آخر.');
        }
    }
}
