<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryBatchModel extends BaseModel
{
    use Auditable;

    protected $table = 'inventory_batches';

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity' => 'float',
            'unit_cost' => 'float',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItemModel::class, 'inventory_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereNull('expiry_date')
                ->orWhereDate('expiry_date', '>=', now()->toDateString());
        });
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString());
    }

    /**
     * First-expiry-first-out: the batch closest to expiring is issued first,
     * which is how a pharmacy avoids writing off stock.
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expiry_date === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }
}
