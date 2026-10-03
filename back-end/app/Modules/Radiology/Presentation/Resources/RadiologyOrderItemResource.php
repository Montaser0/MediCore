<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel
 */
final class RadiologyOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->radiologyOrder?->order;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'clinical_indication' => $this->radiologyOrder?->clinical_indication,
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
                'code' => $this->service?->code,
                'modality' => $this->service?->modality,
                'price' => $this->service?->price,
                'requires_contrast' => $this->service?->requires_contrast,
            ]),
            'studies' => $this->whenLoaded('studies', fn () => RadiologyStudyResource::collection($this->studies)->resolve()),
            'order' => $order === null ? null : [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'priority' => $order->priority->value,
                'status' => $order->status->value,
                'ordered_at' => $order->ordered_at?->toIso8601String(),
                'ordered_by' => $order->orderedBy?->displayName(),
            ],
            'patient' => $order?->encounter?->patient === null ? null : [
                'id' => $order->encounter->patient->id,
                'mrn' => $order->encounter->patient->mrn,
                'full_name' => $order->encounter->patient->full_name,
                'gender' => $order->encounter->patient->gender->value,
                'age' => $order->encounter->patient->age,
                'encounter_id' => $order->encounter->id,
            ],
        ];
    }
}
