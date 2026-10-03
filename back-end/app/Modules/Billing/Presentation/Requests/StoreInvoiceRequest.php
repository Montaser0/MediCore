<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StoreInvoiceRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::BILLING_INVOICES_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Either bill named charges, or everything outstanding for a patient.
            'charge_ids' => ['nullable', 'array', 'min:1'],
            'charge_ids.*' => ['integer', 'exists:charges,id'],
            'patient_id' => ['required_without:charge_ids', 'nullable', 'integer', 'exists:patients,id'],
            'encounter_id' => ['nullable', 'integer', 'exists:encounters,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'issue' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
