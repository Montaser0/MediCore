<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Modules\Pharmacy\Infrastructure\Persistence\Models\DrugCatalogueItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use Illuminate\Support\Collection;

/**
 * Applies one official price bulletin onto the national catalogue.
 * The selling price on this pharmacy's shelf changes only when asked.
 * The active ingredient is copied onto the shelf so alternatives can find it.
 */
final class ApplyPriceBulletinAction
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{matched: int, updated: int, shelf_updated: int, missed: int}
     */
    public function execute(int $hospitalId, string $label, bool $updateShelf, array $rows): array
    {
        $matched = 0;
        $shelfUpdated = 0;
        $missed = 0;
        $label = mb_substr(trim($label), 0, 120);

        foreach ($rows as $row) {
            $items = $this->match($row);
            if ($items->isEmpty()) {
                $missed++;

                continue;
            }

            $matched += $items->count();
            foreach ($items as $item) {
                $shelfUpdated += $this->apply($item, $hospitalId, $label, $updateShelf, $row);
            }
        }

        return [
            'matched' => $matched,
            'updated' => $matched,
            'shelf_updated' => $shelfUpdated,
            'missed' => $missed,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return Collection<int, DrugCatalogueItemModel>
     */
    private function match(array $row): Collection
    {
        $barcode = $this->text($row['barcode'] ?? null);
        if ($barcode !== '') {
            $byBarcode = DrugCatalogueItemModel::query()->where('barcode', $barcode)->get();
            if ($byBarcode->isNotEmpty()) {
                return $byBarcode;
            }
        }

        $source = $this->text($row['source_record_id'] ?? null);
        if ($source !== '') {
            $item = DrugCatalogueItemModel::query()->where('source_record_id', $source)->first();
            if ($item instanceof DrugCatalogueItemModel) {
                return new Collection([$item]);
            }
        }

        $name = $this->text($row['name'] ?? null);
        if ($name === '') {
            return new Collection;
        }

        $company = $this->text($row['company'] ?? null);
        $query = DrugCatalogueItemModel::query()->where('name', $name);
        if ($company !== '') {
            $query->where(function ($inner) use ($company): void {
                $inner->where('company', $company)->orWhere('company_ar', $company);
            });
        }

        $found = $query->limit(3)->get();
        if ($found->count() !== 1) {
            return new Collection;
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function apply(
        DrugCatalogueItemModel $item,
        int $hospitalId,
        string $label,
        bool $updateShelf,
        array $row,
    ): int {
        $price = round((float) $row['official_price'], 2);
        $composition = $this->text($row['composition'] ?? null);
        $generic = $this->text($row['generic_name'] ?? null);

        $item->official_price = $price;
        $item->bulletin_label = $label;
        $item->bulletin_updated_at = now();
        if ($composition !== '') {
            $item->composition = $composition;
        }
        if ($generic !== '') {
            $item->generic_name = $generic;
        }
        $item->save();

        if ($generic !== '') {
            MedicationModel::query()
                ->where('catalogue_item_id', $item->getKey())
                ->update(['generic_name' => mb_substr($generic, 0, 255)]);
        }

        if (! $updateShelf) {
            return 0;
        }

        return MedicationModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('catalogue_item_id', $item->getKey())
            ->update(['price' => $price]);
    }

    private function text(mixed $value): string
    {
        return trim((string) $value);
    }
};
