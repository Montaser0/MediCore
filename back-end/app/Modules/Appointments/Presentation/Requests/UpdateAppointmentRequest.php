<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class UpdateAppointmentRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::APPOINTMENTS_UPDATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'clinic_id' => ['sometimes', 'nullable', 'integer', 'exists:clinics,id'],
            'doctor_id' => ['sometimes', 'nullable', 'integer', 'exists:doctors,id'],
            'scheduled_at' => ['sometimes', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
