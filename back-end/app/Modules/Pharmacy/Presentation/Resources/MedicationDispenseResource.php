<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationDispenseModel
 */
final class MedicationDispenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dispense_number' => $this->dispense_number,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'batch_number' => $this->batch_number,
            'batch_expiry_date' => $this->batch_expiry_date?->toDateString(),
            'dispensed_at' => $this->dispensed_at?->toIso8601String(),
            'prescription_id' => $this->prescription_id,
            'prescription_item_id' => $this->prescription_item_id,
            'medication' => $this->whenLoaded('medication', fn () => [
                'id' => $this->medication?->id,
                'name' => $this->medication?->displayName(),
                'trade_name' => $this->medication?->name,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'dispensed_by' => $this->whenLoaded('dispensedBy', fn () => $this->dispensedBy?->displayName()),
            'notes' => $this->notes,
        ];
    }
}
