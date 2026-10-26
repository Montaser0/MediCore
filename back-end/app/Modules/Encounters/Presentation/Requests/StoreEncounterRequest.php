<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Encounters\Domain\Enums\EncounterType;
use Illuminate\Validation\Rule;

final class StoreEncounterRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::ENCOUNTERS_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'type' => ['required', Rule::in(EncounterType::values())],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'reason' => ['nullable', 'string'],
            'chief_complaint' => ['nullable', 'string'],
            'allow_parallel' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array', 'max:50'],
            'items.*' => [
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }
                    $written = trim((string) ($value['name'] ?? ''));
                    if (empty($value['medication_id']) && empty($value['catalogue_item_id']) && empty($value['service_id']) && $written === '') {
                        $fail('حدد البند قبل التحويل.');
                    }
                },
            ],
            'items.*.medication_id' => ['nullable', 'integer', 'exists:medications,id'],
            'items.*.catalogue_item_id' => ['nullable', 'integer', 'exists:drug_catalogue_items,id'],
            'items.*.service_type' => ['nullable', 'string', 'max:100'],
            'items.*.service_id' => ['nullable', 'integer'],
            'items.*.with_injection' => ['nullable', 'boolean'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.dose' => ['nullable', 'string', 'max:100'],
            'items.*.frequency' => ['nullable', 'string', 'max:100'],
            'items.*.duration' => ['nullable', 'string', 'max:100'],
            'items.*.route' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
