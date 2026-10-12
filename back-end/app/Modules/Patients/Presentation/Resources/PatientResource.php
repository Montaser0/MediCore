<?php

declare(strict_types=1);

namespace App\Modules\Patients\Presentation\Resources;

use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PatientModel
 */
final class PatientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'mrn' => $this->mrn,

            'first_name' => $this->first_name,
            'father_name' => $this->father_name,
            'grandfather_name' => $this->grandfather_name,
            'last_name' => $this->last_name,
            'name_en' => $this->name_en,
            'mother_name' => $this->mother_name,
            'full_name' => $this->full_name,

            'gender' => $this->gender->value,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,

            'national_id' => $this->national_id,
            'phone' => $this->phone,
            'phone_secondary' => $this->phone_secondary,
            'email' => $this->email,

            'address' => $this->address,
            'city' => $this->city,
            'nationality' => $this->nationality,

            'blood_type' => $this->blood_type,
            'marital_status' => $this->marital_status,
            'occupation' => $this->occupation,

            'status' => $this->status->value,
            'notes' => $this->notes,

            'encounters_count' => $this->when(isset($this->encounters_count), fn () => (int) $this->encounters_count),
            'department' => $this->whenLoaded('latestEncounter', fn () => $this->latestEncounter?->department === null ? null : [
                'id' => $this->latestEncounter->department->id,
                'name' => $this->latestEncounter->department->name,
            ]),
            'clinic' => $this->whenLoaded('latestEncounter', fn () => $this->latestEncounter?->clinic === null ? null : [
                'id' => $this->latestEncounter->clinic->id,
                'name' => $this->latestEncounter->clinic->name,
            ]),

            'contacts' => PatientContactResource::collection($this->whenLoaded('contacts')),
            'allergies' => PatientAllergyResource::collection($this->whenLoaded('allergies')),
            'conditions' => PatientConditionResource::collection($this->whenLoaded('conditions')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
