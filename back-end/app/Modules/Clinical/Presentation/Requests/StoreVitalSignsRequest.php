<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Validator;

final class StoreVitalSignsRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::CLINICAL_VITALS_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'systolic_bp' => ['nullable', 'integer', 'between:40,300'],
            'diastolic_bp' => ['nullable', 'integer', 'between:20,200'],
            'heart_rate' => ['nullable', 'integer', 'between:20,300'],
            'respiratory_rate' => ['nullable', 'integer', 'between:4,80'],
            'oxygen_saturation' => ['nullable', 'numeric', 'between:20,100'],
            'weight' => ['nullable', 'numeric', 'between:0.5,500'],
            'height' => ['nullable', 'numeric', 'between:20,250'],
            'recorded_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $measurements = array_filter($this->only([
                'temperature', 'systolic_bp', 'diastolic_bp', 'heart_rate',
                'respiratory_rate', 'oxygen_saturation', 'weight', 'height',
            ]), static fn ($value) => $value !== null && $value !== '');

            if ($measurements === []) {
                $validator->errors()->add('temperature', 'سجّل قياساً واحداً على الأقل.');
            }

            if ($this->filled(['systolic_bp', 'diastolic_bp'])
                && $this->integer('diastolic_bp') >= $this->integer('systolic_bp')) {
                $validator->errors()->add('diastolic_bp', 'الضغط الانبساطي يجب أن يكون أقل من الانقباضي.');
            }
        });
    }
}
