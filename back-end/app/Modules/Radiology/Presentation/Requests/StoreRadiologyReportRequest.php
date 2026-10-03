<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Validator;

final class StoreRadiologyReportRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::RADIOLOGY_REPORTS_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'findings' => ['nullable', 'string', 'max:50000'],
            'impression' => ['nullable', 'string', 'max:50000'],
            'is_critical' => ['nullable', 'boolean'],
            'finalise' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // A draft may be partial, but a report released to the doctor must
            // carry an impression.
            if ($this->boolean('finalise') && ! $this->filled('impression')) {
                $validator->errors()->add('impression', 'التقرير النهائي يحتاج خلاصة.');
            }
        });
    }

    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        // Finalising is the release gate, so it needs the verify permission.
        return ! $this->boolean('finalise')
            || (bool) $this->user()?->can(Permissions::RADIOLOGY_REPORTS_VERIFY);
    }
}
