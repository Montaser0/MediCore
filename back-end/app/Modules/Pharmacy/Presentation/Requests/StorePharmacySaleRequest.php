<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Pharmacy\Application\Support\MedicinePack;
use Illuminate\Validation\Rule;

final class StorePharmacySaleRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PHARMACY_DISPENSING_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.medication_id' => ['required', 'integer', 'exists:medications,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit' => ['nullable', 'string', Rule::in([MedicinePack::BOX, MedicinePack::STRIP, MedicinePack::TABLET])],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'integer', 'exists:pharmacy_customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
        ];
    }
}
