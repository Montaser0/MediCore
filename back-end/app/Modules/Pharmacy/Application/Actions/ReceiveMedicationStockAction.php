<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Modules\Inventory\Application\Actions\ReceiveStockAction;
use App\Modules\Pharmacy\Application\Support\MedicationStockRules;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use Illuminate\Support\Facades\DB;

/**
 * Receives a batch into the pharmacy warehouse. The shelf column on the
 * medicine stays untouched; stock_levels is the balance that changes.
 */
final class ReceiveMedicationStockAction
{
    public function __construct(
        private readonly MedicationStockRules $rules,
        private readonly ProvisionMedicationStockAction $provision,
        private readonly ReceiveStockAction $receive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(MedicationModel $medication, int $hospitalId, array $data): MedicationModel
    {
        return DB::transaction(function () use ($medication, $hospitalId, $data): MedicationModel {
            $locked = MedicationModel::query()->whereKey($medication->getKey())->lockForUpdate()->firstOrFail();
            $this->rules->owned($locked, $hospitalId);

            $warehouseId = (int) $data['warehouse_id'];
            $this->rules->pharmacyWarehouse($warehouseId, $hospitalId);

            if ($locked->inventory_item_id === null) {
                $this->provision->execute(
                    $locked,
                    $hospitalId,
                    isset($data['unit']) ? (string) $data['unit'] : null,
                    isset($data['barcode']) ? (string) $data['barcode'] : null,
                    $warehouseId,
                );
                $locked->refresh();
            }

            if (array_key_exists('price', $data) && $data['price'] !== null && $data['price'] !== '') {
                $locked->price = round((float) $data['price'], 2);
                $locked->save();
            }

            $this->receive->execute([
                'inventory_item_id' => (int) $locked->inventory_item_id,
                'warehouse_id' => $warehouseId,
                'batch_number' => (string) $data['batch_number'],
                'expiry_date' => $data['expiry_date'],
                'quantity' => (float) $data['quantity'],
                'unit_cost' => $data['unit_cost'] ?? 0,
                'reference_type' => 'medication',
                'reference_id' => (int) $locked->getKey(),
                'notes' => $data['notes'] ?? null,
            ]);

            return $locked->refresh();
        });
    }
}
