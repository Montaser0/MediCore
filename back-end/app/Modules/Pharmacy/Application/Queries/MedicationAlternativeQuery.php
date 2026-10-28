<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Queries;

use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryBatchModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;

final class MedicationAlternativeQuery
{
    /**
     * Other shelf medicines with the same active ingredient and stock on hand.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forMedication(MedicationModel $medication): array
    {
        $needle = $this->normalize($this->ingredient($medication));
        if ($needle === '') {
            return [];
        }

        $rows = MedicationModel::query()
            ->with('inventoryItem')
            ->where('hospital_id', $medication->hospital_id)
            ->active()
            ->whereKeyNot($medication->getKey())
            ->whereNotNull('generic_name')
            ->orderBy('name')
            ->get();

        $matches = [];
        foreach ($rows as $row) {
            if ($this->normalize((string) $row->generic_name) !== $needle) {
                continue;
            }

            $onHand = $this->onHand($row);
            if ($onHand <= 0) {
                continue;
            }

            $matches[] = [
                'id' => $row->id,
                'name' => $row->displayName(),
                'generic_name' => $row->generic_name,
                'strength' => $row->strength,
                'manufacturer' => $row->manufacturer,
                'barcode' => $row->barcode,
                'price' => $row->price,
                'price_strip' => $row->price_strip,
                'price_tablet' => $row->price_tablet,
                'strips_per_box' => $row->strips_per_box,
                'tablets_per_strip' => $row->tablets_per_strip,
                'on_hand' => $onHand,
                'expiry_date' => $this->nearestExpiry($row),
            ];
        }

        return $matches;
    }

    private function ingredient(MedicationModel $medication): string
    {
        $generic = trim((string) $medication->generic_name);
        if ($generic !== '') {
            return $generic;
        }

        $medication->loadMissing('catalogueItem');

        return trim((string) $medication->catalogueItem?->generic_name);
    }

    private function onHand(MedicationModel $medication): float
    {
        if ($medication->inventory_item_id === null) {
            return round((float) $medication->quantity, 2);
        }

        return round((float) ($medication->inventoryItem?->totalQuantity() ?? 0), 2);
    }

    private function nearestExpiry(MedicationModel $medication): ?string
    {
        if ($medication->inventory_item_id === null) {
            return null;
        }

        $expiry = InventoryBatchModel::query()
            ->where('inventory_item_id', $medication->inventory_item_id)
            ->available()
            ->notExpired()
            ->fefo()
            ->value('expiry_date');

        if ($expiry === null) {
            return null;
        }

        return substr((string) $expiry, 0, 10);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $collapsed = preg_replace('/\s+/u', ' ', $value);

        return $collapsed === null ? $value : $collapsed;
    }
};
