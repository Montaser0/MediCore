<?php

declare(strict_types=1);

namespace App\Modules\Patients\Presentation\Resources;

use App\Modules\Patients\Infrastructure\Persistence\Models\PatientConditionModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PatientConditionModel
 */
final class PatientConditionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'condition_name' => $this->condition_name,
            'diagnosed_at' => $this->diagnosed_at?->toDateString(),
            'status' => $this->status->value,
            'notes' => $this->notes,
        ];
    }
}
