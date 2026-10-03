<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionModel
 */
final class PrescriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prescription_number' => $this->prescription_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'prescribed_at' => $this->prescribed_at?->toIso8601String(),
            'encounter_id' => $this->encounter_id,
            'order_id' => $this->order_id,
            'items' => PrescriptionItemResource::collection($this->whenLoaded('items')),
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor?->id,
                'name' => $this->doctor?->display_name,
            ]),
            'patient' => $this->whenLoaded('encounter', fn () => $this->encounter?->patient === null ? null : [
                'id' => $this->encounter->patient->id,
                'mrn' => $this->encounter->patient->mrn,
                'full_name' => $this->encounter->patient->full_name,
                'age' => $this->encounter->patient->age,
                'allergies' => $this->encounter->patient->relationLoaded('allergies')
                    ? $this->encounter->patient->allergies->pluck('allergen')->all()
                    : null,
            ]),
        ];
    }
}
