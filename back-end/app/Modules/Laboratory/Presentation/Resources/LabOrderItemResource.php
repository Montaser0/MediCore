<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of laboratory work: the test, its sample and its result.
 *
 * @mixin \App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel
 */
final class LabOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->labOrder?->order;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'test' => $this->whenLoaded('test', fn () => [
                'id' => $this->test?->id,
                'name' => $this->test?->name,
                'code' => $this->test?->code,
                'sample_type' => $this->test?->sample_type,
                'unit' => $this->test?->unit,
                'price' => $this->test?->price,
                'is_panel' => $this->test?->is_panel,
            ]),
            'samples' => $this->whenLoaded('samples', fn () => LabSampleResource::collection($this->samples)->resolve()),
            'result' => $this->whenLoaded('result', fn () => $this->result === null
                ? null
                : (new LabResultResource($this->result))->resolve()),
            'order' => $order === null ? null : [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'priority' => $order->priority->value,
                'status' => $order->status->value,
                'ordered_at' => $order->ordered_at?->toIso8601String(),
                'ordered_by' => $order->orderedBy?->displayName(),
                'clinical_notes' => $order->clinical_notes,
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
