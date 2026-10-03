<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WarehouseModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'warehouses';

    protected function casts(): array
    {
        return [
            'is_pharmacy' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatchModel::class, 'warehouse_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevelModel::class, 'warehouse_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePharmacy(Builder $query): Builder
    {
        return $query->where('is_pharmacy', true);
    }
}
