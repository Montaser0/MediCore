<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Modules\Pharmacy\Infrastructure\Persistence\Models\DrugCatalogueItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The national list describes the medicine. Stock quantity and selling price
 * stay empty until the pharmacist enters them.
 */
final class CatalogueDrugAction
{
    /**
     * Put a catalogue medicine on the pharmacy shelf.
     * Quantity and price are the values the pharmacist typed.
     */
    public function stock(
        DrugCatalogueItemModel $item,
        int $hospitalId,
        float $quantity,
        float $price,
        ?string $barcode,
    ): MedicationModel {
        return DB::transaction(function () use ($item, $hospitalId, $quantity, $price, $barcode): MedicationModel {
            $medication = $this->locked($item, $hospitalId);
            $identity = $this->identity($item);

            if ($medication === null) {
                return $this->insertOrLoad([
                    ...$identity,
                    'hospital_id' => $hospitalId,
                    'barcode' => $barcode,
                    'quantity' => round($quantity, 2),
                    'price' => round($price, 2),
                    'requires_prescription' => false,
                    'is_active' => true,
                ], $hospitalId, $item);
            }

            $medication->fill($identity);
            $medication->quantity = round($quantity, 2);
            $medication->price = round($price, 2);
            if ($barcode !== null && $barcode !== '') {
                $medication->barcode = $barcode;
            }
            $medication->is_active = true;
            $medication->save();

            return $medication;
        });
    }

    /**
     * Make the medicine prescribable. Does not invent a shelf quantity.
     */
    public function adopt(DrugCatalogueItemModel $item, int $hospitalId, ?float $price = null): MedicationModel
    {
        return DB::transaction(function () use ($item, $hospitalId, $price): MedicationModel {
            $medication = $this->locked($item, $hospitalId);

            if ($medication !== null) {
                if ($price !== null) {
                    $medication->price = round($price, 2);
                    $medication->save();
                }

                return $medication;
            }

            return $this->insertOrLoad([
                ...$this->identity($item),
                'hospital_id' => $hospitalId,
                'quantity' => 0,
                'price' => round($price ?? 0, 2),
                'requires_prescription' => $item->product_type === 'دواء',
                'is_active' => true,
            ], $hospitalId, $item);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertOrLoad(array $attributes, int $hospitalId, DrugCatalogueItemModel $item): MedicationModel
    {
        try {
            return MedicationModel::query()->create($attributes);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = MedicationModel::query()
                ->where('hospital_id', $hospitalId)
                ->where('catalogue_item_id', $item->getKey())
                ->first();

            if ($existing instanceof MedicationModel) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function locked(DrugCatalogueItemModel $item, int $hospitalId): ?MedicationModel
    {
        return MedicationModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('catalogue_item_id', $item->getKey())
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(DrugCatalogueItemModel $item): array
    {
        $generic = trim((string) $item->generic_name);

        return [
            'catalogue_item_id' => $item->getKey(),
            'name' => $item->name,
            'generic_name' => $generic === '' ? null : mb_substr($generic, 0, 255),
            'strength' => $item->strength,
            'dosage_form' => $item->dosage_form,
            'pack' => $item->pack,
            'manufacturer' => $item->companyLabel() !== '' ? $item->companyLabel() : null,
            'category' => $item->product_type,
        ];
    }
}
