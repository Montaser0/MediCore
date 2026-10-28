<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel
 */
final class MedicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'generic_name' => $this->generic_name,
            'strength' => $this->strength,
            'dosage_form' => $this->dosage_form,
            'pack' => $this->pack,
            'route' => $this->route,
            'barcode' => $this->barcode,
            'category' => $this->category,
            'manufacturer' => $this->manufacturer,
            'catalogue_item_id' => $this->catalogue_item_id,
            'price' => $this->price,
            'price_strip' => $this->price_strip,
            'price_tablet' => $this->price_tablet,
            'strips_per_box' => $this->strips_per_box,
            'tablets_per_strip' => $this->tablets_per_strip,
            'quantity' => $this->quantity,
            'reorder_level' => $this->reorder_level,
            'hospital_id' => $this->hospital_id,
            'requires_prescription' => $this->requires_prescription,
            'is_active' => $this->is_active,
            'inventory_item_id' => $this->inventory_item_id,
            'stock_ready' => $this->inventory_item_id !== null,
            'stock' => $this->whenLoaded('inventoryItem', fn () => $this->inventoryItem === null ? null : [
                'inventory_item_id' => $this->inventoryItem->id,
                'sku' => $this->inventoryItem->sku,
                'unit' => $this->inventoryItem->unit,
                'quantity_on_hand' => $this->inventoryItem->totalQuantity(),
            ]),
        ];
    }
}
