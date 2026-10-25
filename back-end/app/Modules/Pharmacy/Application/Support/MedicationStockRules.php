<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Support;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;

/**
 * A local medicine and the warehouse that holds it must belong to the same
 * facility, and medicine only sits in a pharmacy warehouse.
 */
final class MedicationStockRules
{
    public function owned(MedicationModel $medication, int $hospitalId): void
    {
        if ($medication->hospital_id !== null && (int) $medication->hospital_id !== $hospitalId) {
            throw BusinessRuleException::make('الدواء لا يتبع هذه المنشأة.');
        }
    }

    public function pharmacyWarehouse(int $warehouseId, int $hospitalId): WarehouseModel
    {
        $warehouse = WarehouseModel::query()->find($warehouseId);

        if (! $warehouse instanceof WarehouseModel || ! $warehouse->is_active || (int) $warehouse->hospital_id !== $hospitalId) {
            throw BusinessRuleException::make('المستودع غير متاح لهذه المنشأة.');
        }

        if (! $warehouse->is_pharmacy) {
            throw BusinessRuleException::make('الدواء يُخزَّن في مستودع الصيدلية.');
        }

        return $warehouse;
    }
}
