<?php

declare(strict_types=1);

namespace App\Modules\Patients\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StorePatientContactRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PATIENTS_UPDATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'is_emergency_contact' => ['sometimes', 'boolean'],
        ];
    }
}
