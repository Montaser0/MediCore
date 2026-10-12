<?php

declare(strict_types=1);

namespace App\Modules\Departments\Infrastructure\Persistence\Models;

use App\Core\Cache\ReferenceCatalog;
use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Departments\Domain\Enums\DepartmentType;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $hospital_id
 * @property string $name
 * @property string $code
 * @property DepartmentType $type
 */
final class DepartmentModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'departments';

    protected $casts = [
        'type' => DepartmentType::class,
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        $forget = static function (self $department): void {
            ReferenceCatalog::forget((int) $department->hospital_id);
        };

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * @return BelongsTo<HospitalModel, $this>
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(HospitalModel::class, 'hospital_id');
    }

    /**
     * @return HasMany<ClinicModel, $this>
     */
    public function clinics(): HasMany
    {
        return $this->hasMany(ClinicModel::class, 'department_id');
    }

    /**
     * @return HasMany<StaffModel, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(StaffModel::class, 'department_id');
    }

    /**
     * @param  Builder<DepartmentModel>  $query
     * @return Builder<DepartmentModel>
     */
    public function scopeOfType(Builder $query, DepartmentType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * @param  Builder<DepartmentModel>  $query
     * @return Builder<DepartmentModel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
