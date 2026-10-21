<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class GenerateItemBarcodesRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::INVENTORY_ITEMS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
