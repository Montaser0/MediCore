<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StorePrescriptionRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PHARMACY_PRESCRIPTIONS_CREATE;

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

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.medication_id' => ['nullable', 'integer', 'exists:medications,id', 'required_without:items.*.catalogue_item_id'],
            'items.*.catalogue_item_id' => ['nullable', 'integer', 'exists:drug_catalogue_items,id', 'required_without:items.*.medication_id'],
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
            'items.*.medication_id.required_without' => 'اختر الدواء من الدليل.',
            'items.*.catalogue_item_id.required_without' => 'اختر الدواء من الدليل.',
        ];
    }
}
