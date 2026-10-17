<?php

declare(strict_types=1);

namespace App\Modules\Operations\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Operations\Domain\Enums\AnesthesiaType;
use App\Modules\Operations\Domain\Enums\OperationRole;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ScheduleOperationRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::OPERATIONS_SCHEDULE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'operation_type_id' => ['required', 'integer', 'exists:operation_types,id'],
            'theatre_id' => ['nullable', 'integer', 'exists:operation_theatres,id'],
            'scheduled_at' => ['nullable', 'date'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'anesthesia_type' => ['nullable', Rule::in(AnesthesiaType::values())],
            'pre_op_diagnosis' => ['nullable', 'string', 'max:2000'],
            'pre_op_notes' => ['nullable', 'string'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'addition' => ['nullable', 'numeric', 'min:0'],
            'team' => ['required', 'array', 'min:1'],
            'team.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'team.*.role' => ['required', Rule::in(OperationRole::values())],
            'team.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * A booking without a named surgeon is not a booking.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $roles = collect($this->input('team', []))->pluck('role');

            if (! $roles->contains(OperationRole::Surgeon->value)) {
                $validator->errors()->add('team', 'يجب أن يضم الفريق جراحاً.');
            }
        });
    }
}
