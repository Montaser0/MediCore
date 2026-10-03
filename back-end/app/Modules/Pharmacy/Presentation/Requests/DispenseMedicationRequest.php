<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class DispenseMedicationRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::PHARMACY_DISPENSING_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            // Left out, the pharmacy picks the batch closest to expiry.
            'batch_id' => ['nullable', 'integer', 'exists:inventory_batches,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
