<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyCounterTicketModel
 */
final class PharmacyCounterTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'encounter_id' => $this->encounter_id,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient === null ? null : [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
            ]),
        ];
    }
}
