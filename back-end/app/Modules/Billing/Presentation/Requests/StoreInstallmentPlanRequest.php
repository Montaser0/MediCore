<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class StoreInstallmentPlanRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::BILLING_INVOICES_CREATE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'count' => ['required', 'integer', 'min:2', 'max:36'],
            'first_due_date' => ['required', 'date', 'after_or_equal:today'],
            'interval_months' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
