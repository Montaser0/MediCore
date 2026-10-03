<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;

final class TransferStockRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::INVENTORY_STOCK_TRANSFER;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'batch_id' => ['nullable', 'integer', 'exists:inventory_batches,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
