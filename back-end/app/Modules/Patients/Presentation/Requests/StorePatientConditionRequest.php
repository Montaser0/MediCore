<?php

declare(strict_types=1);

namespace App\Modules\Patients\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Patients\Domain\Enums\ClinicalRecordStatus;
use Illuminate\Validation\Rule;

final class StorePatientConditionRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PATIENTS_UPDATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'condition_name' => ['required', 'string', 'max:255'],
            'diagnosed_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(ClinicalRecordStatus::conditionValues())],
            'notes' => ['nullable', 'string'],
        ];
    }
}
