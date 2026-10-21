<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacySaleAction;
use Illuminate\Validation\Rule;

final class SavePosMedicationRequest extends ApiFormRequest
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
        $medicationId = $this->route('medication');
        $ignore = is_object($medicationId) ? $medicationId->getKey() : $medicationId;

        return [
            'name' => ['required', 'string', 'max:255'],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('medications', 'barcode')
                    ->where(fn ($query) => $query->where('hospital_id', $hospitalId))
                    ->ignore($ignore),
            ],
            'quantity' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
