<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacySaleAction;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use Illuminate\Validation\Rule;

final class StockCatalogueDrugRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PHARMACY_MEDICATIONS_MANAGE;

    protected function prepareForValidation(): void
    {
        if ($this->exists('barcode') && trim((string) $this->input('barcode')) === '') {
            $this->merge(['barcode' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hospitalId = app(CreatePharmacySaleAction::class)->hospitalId();
        $catalogue = $this->route('catalogueItem');
        $catalogueId = is_object($catalogue) ? $catalogue->getKey() : $catalogue;
        $ignore = MedicationModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('catalogue_item_id', $catalogueId)
            ->value('id');

        return [
            'quantity' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('medications', 'barcode')
                    ->where(fn ($query) => $query->where('hospital_id', $hospitalId))
                    ->ignore($ignore),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'أدخل الكمية.',
            'price.required' => 'أدخل السعر.',
            'barcode.unique' => 'هذا الباركود مستخدم لدواء آخر.',
        ];
    }
}
