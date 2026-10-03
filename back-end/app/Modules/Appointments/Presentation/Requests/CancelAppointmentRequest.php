<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class CancelAppointmentRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::APPOINTMENTS_UPDATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
