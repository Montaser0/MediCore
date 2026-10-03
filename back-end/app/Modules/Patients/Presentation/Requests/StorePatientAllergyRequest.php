<?php

declare(strict_types=1);

namespace App\Modules\Patients\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Patients\Domain\Enums\AllergySeverity;
use App\Modules\Patients\Domain\Enums\ClinicalRecordStatus;
use Illuminate\Validation\Rule;

final class StorePatientAllergyRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PATIENTS_UPDATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'allergen' => ['required', 'string', 'max:200'],
            'reaction' => ['nullable', 'string'],
            'severity' => ['sometimes', Rule::in(AllergySeverity::values())],
            'status' => ['sometimes', Rule::in(ClinicalRecordStatus::allergyValues())],
            'notes' => ['nullable', 'string'],
        ];
    }
}
