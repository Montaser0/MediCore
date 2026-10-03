<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Inpatient\Domain\Enums\AdmissionStatus;
use App\Modules\Inpatient\Domain\Enums\DischargeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class AdmissionModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'admissions';

    protected function casts(): array
    {
        return [
            'status' => AdmissionStatus::class,
            'discharge_type' => DischargeType::class,
            'admitted_at' => 'datetime',
            'expected_discharge_at' => 'datetime',
            'discharged_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientModel::class, 'patient_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }

    public function attendingDoctor(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'attending_doctor_id');
    }

    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'admitted_by');
    }

    public function bedAssignments(): HasMany
    {
        return $this->hasMany(BedAssignmentModel::class, 'admission_id');
    }

    public function currentBedAssignment(): HasOne
    {
        return $this->hasOne(BedAssignmentModel::class, 'admission_id')
            ->whereNull('released_at')
            ->latestOfMany('assigned_at');
    }

    /**
     * Length of stay in nights. An open stay is measured up to now, which is
     * what the ward board needs to show.
     */
    public function lengthOfStayDays(): int
    {
        $end = $this->discharged_at ?? now();

        return max(1, (int) $this->admitted_at->diffInDays($end));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AdmissionStatus::Admitted->value,
            AdmissionStatus::Transferred->value,
        ]);
    }
}
