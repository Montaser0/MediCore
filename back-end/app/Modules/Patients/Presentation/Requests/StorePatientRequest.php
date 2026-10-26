<?php

declare(strict_types=1);

namespace App\Modules\Patients\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Patients\Domain\Enums\AllergySeverity;
use App\Modules\Patients\Domain\Enums\ClinicalRecordStatus;
use App\Modules\Patients\Domain\Enums\Gender;
use App\Modules\Patients\Domain\Enums\PatientStatus;
use Illuminate\Validation\Rule;

final class StorePatientRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PATIENTS_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'grandfather_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'mother_name' => ['nullable', 'string', 'max:200'],
            'gender' => ['required', Rule::in(Gender::values())],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'national_id' => ['required', 'string', 'max:100'],
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

            'contacts' => ['sometimes', 'array'],
            'contacts.*.name' => ['required', 'string', 'max:200'],
            'contacts.*.relationship' => ['nullable', 'string', 'max:100'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.alternate_phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.is_emergency_contact' => ['sometimes', 'boolean'],

            'allergies' => ['sometimes', 'array'],
            'allergies.*.allergen' => ['required', 'string', 'max:200'],
            'allergies.*.reaction' => ['nullable', 'string'],
            'allergies.*.severity' => ['sometimes', Rule::in(AllergySeverity::values())],
            'allergies.*.status' => ['sometimes', Rule::in(ClinicalRecordStatus::allergyValues())],
            'allergies.*.notes' => ['nullable', 'string'],

            'conditions' => ['sometimes', 'array'],
            'conditions.*.condition_name' => ['required', 'string', 'max:255'],
            'conditions.*.diagnosed_at' => ['nullable', 'date'],
            'conditions.*.status' => ['sometimes', Rule::in(ClinicalRecordStatus::conditionValues())],
            'conditions.*.notes' => ['nullable', 'string'],
        ];
    }
}
