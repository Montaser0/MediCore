<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 */
final class HospitalModel extends BaseModel
{
    protected $table = 'hospitals';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<DepartmentModel, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(DepartmentModel::class, 'hospital_id');
    }

    /**
     * @return HasMany<StaffModel, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(StaffModel::class, 'hospital_id');
    }
}
