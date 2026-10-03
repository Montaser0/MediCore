<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StoreCarePlanRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::NURSING_NOTES_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admission_id' => ['nullable', 'integer', 'exists:admissions,id'],
            'title' => ['required', 'string', 'max:255'],
            'problem' => ['required', 'string'],
            'goal' => ['nullable', 'string'],
            'interventions' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
