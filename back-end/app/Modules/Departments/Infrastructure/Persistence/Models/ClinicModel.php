<?php

declare(strict_types=1);

namespace App\Modules\Departments\Infrastructure\Persistence\Models;

use App\Core\Cache\ReferenceCatalog;
use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $department_id
 * @property string $name
 * @property string $code
 */
final class ClinicModel extends BaseModel
{
    protected $table = 'clinics';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        $forget = static function (self $clinic): void {
            $hospitalId = DepartmentModel::query()->whereKey($clinic->department_id)->value('hospital_id');
            ReferenceCatalog::forget((int) $hospitalId);
        };

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @return BelongsTo<DepartmentModel, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }

    /**
     * @param  Builder<ClinicModel>  $query
     * @return Builder<ClinicModel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
