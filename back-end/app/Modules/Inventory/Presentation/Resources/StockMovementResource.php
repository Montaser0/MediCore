<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel
 */
final class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity' => $this->quantity,
            'balance_after' => $this->balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'item' => $this->whenLoaded('item', fn () => [
                'id' => $this->item?->id,
                'name' => $this->item?->name,
                'sku' => $this->item?->sku,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'batch_number' => $this->whenLoaded('batch', fn () => $this->batch?->batch_number),
            'performed_by' => $this->whenLoaded('performedBy', fn () => $this->performedBy?->displayName()),
        ];
    }
}
