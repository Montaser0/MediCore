<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodRequestModel
 */
final class BloodRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'clinic_name' => $this->clinic_name,
            'blood_product_id' => $this->blood_product_id,
            'product_name' => $this->product_name,
            'product_label' => $this->product_name ?: $this->product_type,
            'blood_group' => $this->blood_group,
            'units' => $this->units,
            'unit_price' => $this->unit_price,
            'price' => round((float) $this->unit_price * (int) $this->units, 2),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'confirmed_blood_group' => $this->confirmed_blood_group,
            'bag_number' => $this->bag_number,
            'issued_at' => $this->issued_at?->toDateString(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'requested_by' => $this->requestedBy?->actorLabel(),
            'confirmed_by' => $this->confirmedBy?->actorLabel(),
            'patient' => $this->patient === null ? null : [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
                'blood_type' => $this->patient->blood_type,
            ],
        ];
    }
}
