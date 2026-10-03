<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Inpatient\Domain\Enums\DischargeType;
use Illuminate\Validation\Rule;

final class DischargePatientRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::INPATIENT_DISCHARGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'discharge_type' => ['required', Rule::in(DischargeType::values())],
            // The action enforces that a routine discharge has a summary; the
            // other types may legitimately have none.
            'discharge_summary' => ['nullable', 'string'],
            'discharged_at' => ['nullable', 'date'],
        ];
    }
}
