<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OperationMaterialModel extends BaseModel
{
    protected $table = 'operation_materials';

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_cost' => 'float',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(OperationModel::class, 'operation_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItemModel::class, 'inventory_item_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'recorded_by');
    }
}
