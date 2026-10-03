<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel
 */
final class InventoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'category' => $this->category,
            'unit' => $this->unit,
            'item_type' => $this->item_type->value,
            'item_type_label' => $this->item_type->label(),
            'minimum_stock' => $this->minimum_stock,
            'reorder_quantity' => $this->reorder_quantity,
            'is_batch_tracked' => $this->is_batch_tracked,
            'is_active' => $this->is_active,
            'stock' => $this->whenLoaded('stockLevels', fn () => [
                'total' => (float) $this->stockLevels->sum('quantity'),
                'by_warehouse' => $this->stockLevels->map(fn ($level) => [
                    'warehouse_id' => $level->warehouse_id,
                    'warehouse' => $level->warehouse?->name,
                    'quantity' => $level->quantity,
                    'available' => $level->availableQuantity(),
                ])->all(),
            ]),
        ];
    }
}
