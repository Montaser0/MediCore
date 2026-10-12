<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StoreDiagnosisCodeRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::DEPARTMENTS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'clinic_id' => ['required', 'integer', 'exists:clinics,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clinic_id.required' => 'اختر العيادة التي يُربط بها التشخيص.',
            'title.required' => 'اكتب اسم التشخيص.',
        ];
    }
}
