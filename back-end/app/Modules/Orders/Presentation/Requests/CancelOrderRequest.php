<?php

declare(strict_types=1);

namespace App\Modules\Orders\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class CancelOrderRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::ORDERS_CANCEL;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
