<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StoreSupplierPaymentRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PHARMACY_MEDICATIONS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purchase_id' => ['required', 'integer', 'exists:pharmacy_purchases,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
