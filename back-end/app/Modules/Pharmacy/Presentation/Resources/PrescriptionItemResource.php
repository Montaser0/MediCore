<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionItemModel
 */
final class PrescriptionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medication' => $this->whenLoaded('medication', fn () => [
                'id' => $this->medication?->id,
                'name' => $this->medication?->displayName(),
                'price' => $this->medication?->price,
                'inventory_item_id' => $this->medication?->inventory_item_id,
            ]),
            'dose' => $this->dose,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'route' => $this->route,
            'sig' => $this->sig(),
            'quantity' => $this->quantity,
            'dispensed_quantity' => $this->dispensed_quantity,
            'remaining_quantity' => $this->remainingQuantity(),
            'instructions' => $this->instructions,
        ];
    }
}
