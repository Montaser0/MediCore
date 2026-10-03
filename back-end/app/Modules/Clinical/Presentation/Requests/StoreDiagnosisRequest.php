<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Clinical\Domain\Enums\DiagnosisType;
use Illuminate\Validation\Rule;

final class StoreDiagnosisRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::CLINICAL_DIAGNOSIS_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'diagnosis_code_id' => ['nullable', 'integer', 'exists:diagnosis_codes,id'],
            'diagnosis_name' => ['required_without:diagnosis_code_id', 'nullable', 'string', 'max:255'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'type' => ['nullable', Rule::in(DiagnosisType::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'diagnosed_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
