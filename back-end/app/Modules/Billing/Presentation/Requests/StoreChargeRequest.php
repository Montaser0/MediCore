<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StoreChargeRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::BILLING_CHARGES_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'description' => ['required_without:service_id', 'nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
            'unit_price' => ['required_without:service_id', 'nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
