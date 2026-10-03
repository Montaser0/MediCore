<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Encounters\Domain\Enums\EncounterType;
use Illuminate\Validation\Rule;

final class StoreEncounterRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::ENCOUNTERS_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'type' => ['required', Rule::in(EncounterType::values())],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'reason' => ['nullable', 'string'],
            'chief_complaint' => ['nullable', 'string'],
            'allow_parallel' => ['sometimes', 'boolean'],
        ];
    }
}
