<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Laboratory\Domain\Enums\LabTestCategory;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabTestModel;
use Illuminate\Validation\Rule;

final class StoreLabTestRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::LABORATORY_TESTS_MANAGE;

    protected function prepareForValidation(): void
    {
        if (trim((string) $this->input('code', '')) === '') {
            $this->merge(['code' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $test = $this->route('lab_test');
        $testId = $test instanceof LabTestModel ? $test->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('lab_tests', 'code')->ignore($testId)],
            'category' => ['required', Rule::in(LabTestCategory::values())],
            'sample_type' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'reference_range' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'turnaround_hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'is_panel' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],

            'parameters' => ['nullable', 'array', 'max:60'],
            'parameters.*.name' => ['required', 'string', 'max:255'],
            'parameters.*.code' => ['nullable', 'string', 'max:50'],
            'parameters.*.unit' => ['nullable', 'string', 'max:50'],
            'parameters.*.reference_range' => ['nullable', 'string', 'max:255'],
            'parameters.*.normal_low' => ['nullable', 'numeric'],
            'parameters.*.normal_high' => ['nullable', 'numeric'],
            'parameters.*.critical_low' => ['nullable', 'numeric'],
            'parameters.*.critical_high' => ['nullable', 'numeric'],
        ];
    }
}
