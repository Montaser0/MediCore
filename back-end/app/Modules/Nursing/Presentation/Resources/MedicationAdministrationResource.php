<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Nursing\Infrastructure\Persistence\Models\MedicationAdministrationModel
 */
final class MedicationAdministrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'administered_at' => $this->administered_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'dose' => $this->dose,
            'route' => $this->route,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'encounter_id' => $this->encounter_id,
            'prescription_item_id' => $this->prescription_item_id,
            'medication' => $this->whenLoaded('medication', fn () => [
                'id' => $this->medication?->id,
                'name' => $this->medication?->displayName(),
            ]),
            'administered_by' => $this->whenLoaded('administeredBy', fn () => $this->administeredBy?->displayName()),
        ];
    }
}
