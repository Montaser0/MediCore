<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Laboratory\Domain\Enums\ResultFlag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreLabResultRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::LABORATORY_RESULTS_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'result_value' => ['nullable', 'string', 'max:5000'],
            'unit' => ['nullable', 'string', 'max:50'],
            'reference_range' => ['nullable', 'string', 'max:255'],
            'flag' => ['nullable', Rule::in(ResultFlag::values())],
            'comment' => ['nullable', 'string', 'max:5000'],

            'values' => ['nullable', 'array', 'max:60'],
            'values.*.lab_test_parameter_id' => ['required', 'integer', 'exists:lab_test_parameters,id'],
            'values.*.value' => ['nullable', 'string', 'max:255'],
            'values.*.unit' => ['nullable', 'string', 'max:50'],
            'values.*.reference_range' => ['nullable', 'string', 'max:255'],
            'values.*.flag' => ['nullable', Rule::in(ResultFlag::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // A single-value test needs a reading; a panel needs its rows.
            if (! $this->filled('result_value') && $this->input('values', []) === []) {
                $validator->errors()->add('result_value', 'أدخل نتيجة التحليل أو قيمة معيار واحد على الأقل.');
            }
        });
    }
}
