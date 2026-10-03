<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Requests;

use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Inventory\Domain\Enums\ItemType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use Illuminate\Validation\Rule;

final class StoreInventoryItemRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::INVENTORY_ITEMS_MANAGE;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $itemId = $item instanceof InventoryItemModel ? $item->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('inventory_items', 'sku')->ignore($itemId)],
            'barcode' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:150'],
            'unit' => ['required', 'string', 'max:50'],
            'item_type' => ['required', Rule::in(ItemType::values())],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_quantity' => ['nullable', 'numeric', 'min:0'],
            'is_batch_tracked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
