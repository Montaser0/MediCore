<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $staff_id
 * @property string|null $specialty
 */
final class DoctorModel extends BaseModel
{
    protected $table = 'doctors';

    /**
     * @return BelongsTo<StaffModel, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffModel::class, 'staff_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return 'Dr. '.($this->staff?->full_name ?? '');
    }

    /**
     * @param  Builder<DoctorModel>  $query
     * @return Builder<DoctorModel>
     */
    public function scopeInDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->whereHas('staff', fn (Builder $staff) => $staff->where('department_id', $departmentId));
    }
}
