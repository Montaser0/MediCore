<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Numbering\DailyNumber;
use App\Core\Support\RecordLookup;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Encounters\Domain\Enums\EncounterStatus;
use App\Modules\Encounters\Domain\Enums\EncounterType;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $encounter_number
 * @property EncounterType $type
 * @property EncounterStatus $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 */
final class EncounterModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'encounters';

    protected $casts = [
        'type' => EncounterType::class,
        'status' => EncounterStatus::class,
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'follow_up_date' => 'date',
    ];

    protected function encounterNumber(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => DailyNumber::present($value),
            set: fn (?string $value) => [
                'encounter_number' => $value,
                'encounter_serial' => DailyNumber::serial($value),
            ],
        );
    }

    /**
     * Columns the waiting room and visit list actually render.
     *
     * @param  Builder<EncounterModel>  $query
     * @return Builder<EncounterModel>
     */
    public function scopeForList(Builder $query): Builder
    {
        return $query
            ->select([
                'encounters.id',
                'encounters.hospital_id',
                'encounters.patient_id',
                'encounters.encounter_number',
                'encounters.type',
                'encounters.status',
                'encounters.department_id',
                'encounters.clinic_id',
                'encounters.doctor_id',
                'encounters.started_at',
                'encounters.ended_at',
                'encounters.reason',
                'encounters.chief_complaint',
                'encounters.discharge_instructions',
                'encounters.discharge_disposition',
                'encounters.follow_up_date',
                'encounters.created_at',
            ])
            ->with([
                'patient:id,mrn,first_name,father_name,grandfather_name,last_name,name_en,gender,date_of_birth,phone,national_id,blood_type,status',
                'department:id,name,type',
                'clinic:id,name',
                'doctor:id,staff_id,specialty',
                'doctor.staff:id,first_name,last_name',
            ]);
    }

    /**
     * @param  Builder<EncounterModel>  $query
     * @return Builder<EncounterModel>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return RecordLookup::encounters($query, $term);
    }

    /**
     * @return BelongsTo<PatientModel, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientModel::class, 'patient_id');
    }

    /**
     * @return BelongsTo<DepartmentModel, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }

    /**
     * @return BelongsTo<ClinicModel, $this>
     */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(ClinicModel::class, 'clinic_id');
    }

    /**
     * @return BelongsTo<DoctorModel, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorModel::class, 'doctor_id');
    }

    /**
     * @return HasMany<EncounterTransferModel, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(EncounterTransferModel::class, 'encounter_id');
    }

    /**
     * @return HasMany<EncounterStatusHistoryModel, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(EncounterStatusHistoryModel::class, 'encounter_id');
    }

    /**
     * Length of stay, which the PRD wants displayed as "8h 30m" for an
     * outpatient visit and "8 days" for an admission.
     */
    public function durationInMinutes(): ?int
    {
        $start = $this->started_at ?? $this->created_at;

        if ($start === null) {
            return null;
        }

        return (int) $start->diffInMinutes($this->ended_at ?? now());
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * @param  Builder<EncounterModel>  $query
     * @return Builder<EncounterModel>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EncounterStatus::Registered->value,
            EncounterStatus::Waiting->value,
            EncounterStatus::InProgress->value,
        ]);
    }

    /**
     * The clinic waiting list: patients registered or waiting in a department,
     * oldest first.
     *
     * @param  Builder<EncounterModel>  $query
     * @return Builder<EncounterModel>
     */
    public function scopeQueueFor(Builder $query, ?int $departmentId = null, ?int $clinicId = null, ?int $doctorId = null): Builder
    {
        return $query
            ->whereIn('status', [EncounterStatus::Registered->value, EncounterStatus::Waiting->value])
            ->when($departmentId !== null, fn (Builder $inner) => $inner->where('department_id', $departmentId))
            ->when($clinicId !== null, fn (Builder $inner) => $inner->where('clinic_id', $clinicId))
            ->when($doctorId !== null, fn (Builder $inner) => $inner->where('doctor_id', $doctorId))
            ->orderBy('created_at');
    }
}
