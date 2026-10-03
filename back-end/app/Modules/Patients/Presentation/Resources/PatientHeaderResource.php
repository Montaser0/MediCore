<?php

declare(strict_types=1);

namespace App\Modules\Patients\Presentation\Resources;

use App\Modules\Patients\Domain\Enums\AllergySeverity;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact banner shown on every clinical screen. It intentionally carries
 * the allergy warnings so a doctor cannot prescribe without seeing them.
 *
 * @mixin PatientModel
 */
final class PatientHeaderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mrn' => $this->mrn,
            'full_name' => $this->full_name,
            'gender' => $this->gender->value,
            'age' => $this->age,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'phone' => $this->phone,
            'blood_type' => $this->blood_type,
            'status' => $this->status->value,
            'allergies' => $this->whenLoaded('allergies', fn () => $this->allergies->map(fn ($allergy) => [
                'allergen' => $allergy->allergen,
                'severity' => $allergy->severity->value,
                'requires_alert' => $allergy->severity->requiresAlert(),
            ])->all()),
            'has_critical_allergy' => $this->whenLoaded(
                'allergies',
                fn () => $this->allergies->contains(fn ($allergy) => $allergy->severity === AllergySeverity::Severe)
            ),
            'chronic_conditions' => $this->whenLoaded(
                'conditions',
                fn () => $this->conditions->pluck('condition_name')->all()
            ),
            'emergency_contact' => $this->whenLoaded('contacts', function () {
                $contact = $this->contacts->first();

                return $contact === null ? null : [
                    'name' => $contact->name,
                    'relationship' => $contact->relationship,
                    'phone' => $contact->phone,
                ];
            }),
        ];
    }
}
