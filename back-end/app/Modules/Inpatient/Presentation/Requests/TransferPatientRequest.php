<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class TransferPatientRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::INPATIENT_TRANSFER;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'attending_doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
