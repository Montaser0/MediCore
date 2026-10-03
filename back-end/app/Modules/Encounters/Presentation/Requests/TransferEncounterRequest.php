<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class TransferEncounterRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::ENCOUNTERS_TRANSFER;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to_department_id' => ['required', 'integer', 'exists:departments,id'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
