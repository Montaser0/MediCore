<?php

declare(strict_types=1);

namespace App\Modules\Patients\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Patients\Domain\Enums\Gender;
use App\Modules\Patients\Domain\Enums\PatientStatus;
use Illuminate\Validation\Rule;

final class UpdatePatientRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PATIENTS_UPDATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'grandfather_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:200'],
            'mother_name' => ['nullable', 'string', 'max:200'],
            'gender' => ['sometimes', Rule::in(Gender::values())],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'national_id' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_secondary' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'status' => ['sometimes', Rule::in(PatientStatus::values())],
            'notes' => ['nullable', 'string'],
        ];
    }
}
