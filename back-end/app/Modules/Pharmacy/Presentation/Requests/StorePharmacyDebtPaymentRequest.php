<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StorePharmacyDebtPaymentRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PHARMACY_DISPENSING_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:pharmacy_customers,id'],
            'sale_id' => ['nullable', 'integer', 'exists:pharmacy_sales,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
