<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Inventory\Domain\Enums\MovementType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only: a stock movement is a historical fact and is never edited, so
 * corrections are entered as a new adjustment movement.
 */
final class StockMovementModel extends BaseModel
{
    protected $table = 'stock_movements';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'float',
            'balance_after' => 'float',
            'created_at' => 'datetime',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatchModel::class, 'batch_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'performed_by');
    }
}
