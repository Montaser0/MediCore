<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use Illuminate\Validation\Rule;

final class StoreMedicationRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PHARMACY_MEDICATIONS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $medication = $this->route('medication');
        $medicationId = $medication instanceof MedicationModel ? $medication->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:100'],
            'dosage_form' => ['nullable', 'string', 'max:100'],
            'pack' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:150'],
            'manufacturer' => ['nullable', 'string', 'max:80'],
            'medication_category_id' => ['nullable', 'integer', 'exists:medication_categories,id'],
            'inventory_item_id' => [
                'nullable',
                'integer',
                'exists:inventory_items,id',
                Rule::unique('medications', 'inventory_item_id')->ignore($medicationId),
            ],
            'price' => ['nullable', 'numeric', 'min:0'],
            'requires_prescription' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
