<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Inventory\Domain\Enums\MovementType;
use Illuminate\Validation\Rule;

final class AdjustStockRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::INVENTORY_STOCK_ADJUST;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', 'exists:inventory_batches,id'],
            // Signed: negative writes stock off, positive corrects a shortfall.
            'quantity' => ['required', 'numeric'],
            'type' => ['nullable', Rule::in([MovementType::Adjustment->value, MovementType::Expired->value])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
