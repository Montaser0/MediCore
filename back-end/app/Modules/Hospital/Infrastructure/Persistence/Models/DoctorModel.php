<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

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

        if (! Schema::hasTable('clinic_doctor')) {
            return [];
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
     * Doctors linked to this clinic on the doctor row, the employee row, or the
     * clinic roster. A sibling clinic in the same department does not match.
     * Missing clinic columns are skipped so an unfinished migration does not
     * turn the list into an error.
     *
     * @param  Builder<DoctorModel>  $query
     * @return Builder<DoctorModel>
     */
    public function scopeInClinic(Builder $query, int $clinicId): Builder
    {
        $doctorClinic = Schema::hasColumn('doctors', 'clinic_id');
        $staffClinic = Schema::hasColumn('staff', 'clinic_id');
        $roster = Schema::hasTable('clinic_doctor');

        return $query->where(function (Builder $inner) use ($clinicId, $doctorClinic, $staffClinic, $roster): void {
            $matched = false;

            if ($doctorClinic) {
                $inner->orWhere('doctors.clinic_id', $clinicId);
                $matched = true;
            }

            if ($roster) {
                $inner->orWhereHas('clinics', fn (Builder $clinics) => $clinics->whereKey($clinicId));
                $matched = true;
            }

            if ($staffClinic) {
                $inner->orWhereHas('staff', fn (Builder $staff) => $staff->where('clinic_id', $clinicId));
                $matched = true;
            }

            if (! $matched) {
                $inner->whereRaw('0 = 1');
            }
        });
    }
}
