<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Requests;

use App\Core\Auth\CurrentUser;
use App\Core\Authorization\Permissions;
use App\Core\Http\Requests\ApiFormRequest;
use App\Modules\Inventory\Domain\Enums\ItemType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreInventoryItemRequest extends ApiFormRequest
{
    protected ?string $permission = Permissions::INVENTORY_ITEMS_MANAGE;

    protected function prepareForValidation(): void
    {
        if ($this->exists('barcode') && trim((string) $this->input('barcode')) === '') {
            $this->merge(['barcode' => null]);
        }
    }

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
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('inventory_items', 'barcode')->ignore($itemId),
            ],
            'category' => ['nullable', 'string', 'max:150'],
            'unit' => ['required', 'string', 'max:50'],
            'item_type' => ['required', Rule::in($this->isMethod('post') ? [ItemType::Medicine->value, ItemType::General->value] : ItemType::values())],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_quantity' => ['nullable', 'numeric', 'min:0'],
            'is_batch_tracked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'warehouse_id' => [
                $this->isMethod('post') ? 'required' : 'exclude',
                'integer',
                Rule::exists('warehouses', 'id')->where(function ($query): void {
                    $hospitalId = app(CurrentUser::class)->hospitalId();
                    if ($hospitalId !== null) {
                        $query->where('hospital_id', $hospitalId);
                    }
                }),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->isMethod('post') || $validator->errors()->isNotEmpty()) {
                return;
            }

            $warehouse = WarehouseModel::query()->find($this->integer('warehouse_id'));
            if (! $warehouse instanceof WarehouseModel) {
                return;
            }

            $medicine = (string) $this->input('item_type') === ItemType::Medicine->value;

            if ($medicine && ! $warehouse->is_pharmacy) {
                $validator->errors()->add('warehouse_id', 'الدواء يُضاف إلى مستودع الصيدلية.');
            }

            if (! $medicine && $warehouse->is_pharmacy) {
                $validator->errors()->add('warehouse_id', 'الصنف العام يُضاف إلى مستودع غير الصيدلية.');
            }
        });
    }
}
