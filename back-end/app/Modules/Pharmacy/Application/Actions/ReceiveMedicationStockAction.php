<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Modules\Inventory\Application\Actions\ReceiveStockAction;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockLevelModel;
use App\Modules\Pharmacy\Application\Support\MedicationStockRules;
use App\Modules\Pharmacy\Application\Support\MedicinePack;
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
            }
            if (array_key_exists('price_strip', $data) && $data['price_strip'] !== null && $data['price_strip'] !== '') {
                $locked->price_strip = round((float) $data['price_strip'], 2);
            }
            if (array_key_exists('price_tablet', $data) && $data['price_tablet'] !== null && $data['price_tablet'] !== '') {
                $locked->price_tablet = round((float) $data['price_tablet'], 2);
            }

            $strips = max(1, (int) ($data['strips_per_box'] ?? $locked->strips_per_box ?? 1));
            $tablets = max(1, (int) ($data['tablets_per_strip'] ?? $locked->tablets_per_strip ?? 1));
            if (array_key_exists('strips_per_box', $data) || array_key_exists('tablets_per_strip', $data)) {
                $this->applyPack($locked, $strips, $tablets);
            }
            $locked->save();

            $purchaseUnit = MedicinePack::normalize((string) ($data['purchase_unit'] ?? MedicinePack::BOX));
            $baseQuantity = MedicinePack::toBase((float) $data['quantity'], $purchaseUnit, $strips, $tablets);
            $baseCost = MedicinePack::costPerBase((float) ($data['unit_cost'] ?? 0), $purchaseUnit, $strips, $tablets);

            $this->receive->execute([
                'inventory_item_id' => (int) $locked->inventory_item_id,
                'warehouse_id' => $warehouseId,
                'batch_number' => (string) $data['batch_number'],
                'expiry_date' => $data['expiry_date'],
                'quantity' => $baseQuantity,
                'unit_cost' => $baseCost,
                'reference_type' => 'medication',
                'reference_id' => (int) $locked->getKey(),
                'notes' => $data['notes'] ?? null,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Stock is stored in tablets. Changing how many tablets sit in a box
     * rescales what is already on the shelf so the box count stays the same.
     */
    private function applyPack(MedicationModel $medication, int $strips, int $tablets): void
    {
        $old = MedicinePack::per(MedicinePack::BOX, (int) $medication->strips_per_box, (int) $medication->tablets_per_strip);
        $new = MedicinePack::per(MedicinePack::BOX, $strips, $tablets);
        if ($medication->inventory_item_id !== null && $old !== $new) {
            $ratio = $new / $old;
            $itemId = (int) $medication->inventory_item_id;
            foreach (InventoryBatchModel::query()->where('inventory_item_id', $itemId)->lockForUpdate()->get() as $batch) {
                $batch->quantity = round((float) $batch->quantity * $ratio, 2);
                $batch->unit_cost = round((float) $batch->unit_cost / $ratio, 2);
                $batch->save();
            }
            foreach (StockLevelModel::query()->where('inventory_item_id', $itemId)->lockForUpdate()->get() as $level) {
                $level->quantity = round((float) $level->quantity * $ratio, 2);
                $level->save();
            }
            $medication->reorder_level = round((float) $medication->reorder_level * $ratio, 2);
        }

        $medication->strips_per_box = $strips;
        $medication->tablets_per_strip = $tablets;
    }
}
