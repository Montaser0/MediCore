<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $staff_id
 * @property int|null $clinic_id
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

    /**
     * @return BelongsTo<ClinicModel, $this>
     */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(ClinicModel::class, 'clinic_id');
    }

    /**
     * @return BelongsToMany<ClinicModel, $this>
     */
    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(ClinicModel::class, 'clinic_doctor', 'doctor_id', 'clinic_id');
    }

    /**
     * The clinic that supplies this doctor's diagnosis list.
     * The assigned clinic wins; otherwise every clinic listed on the doctor is used.
     *
     * @return list<int>
     */
    public function diagnosisClinicIds(): array
    {
        if ($this->clinic_id !== null) {
            return [(int) $this->clinic_id];
        }

        if (! $this->relationLoaded('clinics')) {
            $this->load('clinics:clinics.id');
        }

        return $this->clinics
            ->map(fn ($clinic) => (int) $clinic->getKey())
            ->unique()
            ->values()
            ->all();
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

    /**
     * Doctors pinned to this clinic, plus department doctors who have no clinic yet.
     * Registration filters by the selected clinic, so an unassigned doctor in the
     * same department would otherwise never appear in the list.
     *
     * @param  Builder<DoctorModel>  $query
     * @return Builder<DoctorModel>
     */
    public function scopeInClinic(Builder $query, int $clinicId): Builder
    {
        $departmentId = ClinicModel::query()->whereKey($clinicId)->value('department_id');

        return $query->where(function (Builder $inner) use ($clinicId, $departmentId): void {
            $inner->where('doctors.clinic_id', $clinicId)
                ->orWhereHas('clinics', fn (Builder $clinics) => $clinics->whereKey($clinicId));

            if ($departmentId === null) {
                return;
            }

            $inner->orWhere(function (Builder $open) use ($departmentId): void {
                $open->whereNull('doctors.clinic_id')
                    ->whereDoesntHave('clinics')
                    ->whereHas(
                        'staff',
                        fn (Builder $staff) => $staff->where('department_id', $departmentId),
                    );
            });
        });
    }
}
