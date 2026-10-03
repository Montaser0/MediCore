<?php

declare(strict_types=1);

namespace App\Modules\Orders\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel
 */
final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'clinical_notes' => $this->clinical_notes,
            'cancellation_reason' => $this->cancellation_reason,
            'ordered_at' => $this->ordered_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'encounter_id' => $this->encounter_id,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department?->id,
                'name' => $this->department?->name,
            ]),
            'ordered_by' => $this->whenLoaded('orderedBy', fn () => [
                'id' => $this->orderedBy?->id,
                'name' => $this->orderedBy?->displayName(),
            ]),
            'patient' => $this->whenLoaded('encounter', fn () => [
                'id' => $this->encounter?->patient?->id,
                'mrn' => $this->encounter?->patient?->mrn,
                'full_name' => $this->encounter?->patient?->full_name,
                'encounter_number' => $this->encounter?->encounter_number,
            ]),
        ];
    }
}
