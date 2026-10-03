<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Billing\Infrastructure\Persistence\Models\ChargeModel
 */
final class ChargeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'total' => $this->total,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'encounter_id' => $this->encounter_id,
            'invoice_id' => $this->invoice_id,
            'is_invoiced' => $this->invoice_id !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
                'code' => $this->service?->code,
            ]),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'mrn' => $this->patient?->mrn,
                'full_name' => $this->patient?->full_name,
            ]),
        ];
    }
}
