<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class ApplyDiscountRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::BILLING_DISCOUNTS_APPLY;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'discount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
