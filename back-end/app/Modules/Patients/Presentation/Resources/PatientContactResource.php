<?php

declare(strict_types=1);

namespace App\Modules\Patients\Presentation\Resources;

use App\Modules\Patients\Infrastructure\Persistence\Models\PatientContactModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PatientContactModel
 */
final class PatientContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'name' => $this->name,
            'relationship' => $this->relationship,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'is_emergency_contact' => $this->is_emergency_contact,
        ];
    }
}
