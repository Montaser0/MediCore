<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StorePharmacyReturnRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PHARMACY_DISPENSING_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sale_id' => ['required', 'integer', 'exists:pharmacy_sales,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sale_item_id' => ['required', 'integer', 'exists:pharmacy_sale_items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
