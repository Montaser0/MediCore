<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StorePrescriptionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can(Permissions::PHARMACY_PRESCRIPTIONS_CREATE) === true
            || $user->can(Permissions::ENCOUNTERS_CREATE) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_draft' => ['nullable', 'boolean'],
            'fulfillment' => ['nullable', 'in:hospital,external'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => [
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }
                    $written = trim((string) ($value['name'] ?? ''));
                    if (empty($value['medication_id']) && empty($value['catalogue_item_id']) && $written === '') {
                        $fail('اختر الدواء من الدليل أو اكتب اسمه.');
                    }
                },
            ],
            'items.*.medication_id' => ['nullable', 'integer', 'exists:medications,id'],
            'items.*.catalogue_item_id' => ['nullable', 'integer', 'exists:drug_catalogue_items,id'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.dose' => ['nullable', 'string', 'max:100'],
            'items.*.frequency' => ['nullable', 'string', 'max:100'],
            'items.*.duration' => ['nullable', 'string', 'max:100'],
            'items.*.route' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.name.max' => 'اسم الدواء أطول من المسموح.',
        ];
    }
}
