<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StorePharmacyPurchaseRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PHARMACY_MEDICATIONS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer', 'exists:pharmacy_suppliers,id'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_phone' => ['nullable', 'string', 'max:40'],
            'invoice_number' => ['required', 'string', 'max:80'],
            'total' => ['required', 'numeric', 'min:0.01'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'received_at' => ['nullable', 'date'],
        ];
    }
}
