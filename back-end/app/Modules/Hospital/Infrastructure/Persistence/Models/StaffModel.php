<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $hospital_id
 * @property int|null $department_id
 * @property string $employee_number
 * @property string $first_name
 * @property string $last_name
 */
final class StaffModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'staff';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * @return BelongsTo<HospitalModel, $this>
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(HospitalModel::class, 'hospital_id');
    }

    /**
     * @return BelongsTo<DepartmentModel, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }

    /**
     * @return HasOne<DoctorModel, $this>
     */
    public function doctor(): HasOne
    {
        return $this->hasOne(DoctorModel::class, 'staff_id');
    }

    /**
     * @return HasOne<UserModel, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(UserModel::class, 'staff_id');
    }
}
