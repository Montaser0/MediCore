<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Assets\Domain\Enums\AssetCategory;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ItAssetModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'it_assets';

    protected function casts(): array
    {
        return [
            'category' => AssetCategory::class,
            'status' => AssetStatus::class,
            'delivered_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ItAssetMovementModel::class, 'it_asset_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->getModel()->getTable().'.is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('asset_code', $term)
                ->orWhere('barcode', $term)
                ->orWhere('asset_code', 'like', '%'.$term.'%')
                ->orWhere('barcode', 'like', '%'.$term.'%')
                ->orWhere('brand', 'like', '%'.$term.'%')
                ->orWhere('model_name', 'like', '%'.$term.'%')
                ->orWhere('location', 'like', '%'.$term.'%')
                ->orWhere('received_by', 'like', '%'.$term.'%');
        });
    }
}
