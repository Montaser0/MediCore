<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class AdmitPatientRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::INPATIENT_ADMIT;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'attending_doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'bed_id' => ['nullable', 'integer', 'exists:beds,id'],
            'admitted_at' => ['nullable', 'date'],
            'expected_discharge_at' => ['nullable', 'date', 'after:admitted_at'],
            'admission_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
