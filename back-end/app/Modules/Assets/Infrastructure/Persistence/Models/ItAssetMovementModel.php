<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ItAssetMovementModel extends BaseModel
{
    protected $table = 'it_asset_movements';

    protected function casts(): array
    {
        return [
            'type' => AssetMovementType::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAssetModel::class, 'it_asset_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'to_department_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'actor_id');
    }
}
