<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class CloseEncounterRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::ENCOUNTERS_DISCHARGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'discharge_instructions' => ['nullable', 'string'],
            'discharge_disposition' => ['nullable', 'string', 'max:100'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string'],
            'cancel' => ['sometimes', 'boolean'],
        ];
    }
}
