<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Inventory\Application\Support\ItemBarcode;
use App\Modules\Inventory\Domain\Enums\ItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventoryItemModel extends BaseModel
{
    protected $table = 'inventory_items';

    protected function casts(): array
    {
        return [
            'item_type' => ItemType::class,
            'minimum_stock' => 'float',
            'reorder_quantity' => 'float',
            'is_batch_tracked' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (blank($item->barcode)) {
                $kind = $item->item_type instanceof ItemType
                    ? $item->item_type
                    : (ItemType::tryFrom((string) $item->item_type) ?? ItemType::General);
                $item->barcode = ItemBarcode::next($kind);
            }
        });
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatchModel::class, 'inventory_item_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevelModel::class, 'inventory_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovementModel::class, 'inventory_item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', '%'.$term.'%')
                ->orWhere('sku', 'like', $term.'%')
                ->orWhere('barcode', $term)
                ->orWhere('category', 'like', '%'.$term.'%');
        });
    }

    public function totalQuantity(?int $warehouseId = null): float
    {
        return (float) $this->stockLevels()
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->sum('quantity');
    }

    public function isBelowMinimum(?int $warehouseId = null): bool
    {
        return $this->totalQuantity($warehouseId) <= (float) $this->minimum_stock;
    }
}
