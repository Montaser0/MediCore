<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Nursing\Domain\Enums\AdministrationStatus;
use Illuminate\Validation\Rule;

final class RecordAdministrationRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::NURSING_MEDICATION_ADMINISTER;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                AdministrationStatus::Given->value,
                AdministrationStatus::Refused->value,
                AdministrationStatus::Omitted->value,
                AdministrationStatus::Held->value,
            ])],
            'administered_at' => ['nullable', 'date', 'before_or_equal:now'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
