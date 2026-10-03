<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StoreProcedureRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::CLINICAL_PROCEDURES_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required_without:service_id', 'nullable', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'outcome' => ['nullable', 'string', 'max:5000'],
            'performed_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
