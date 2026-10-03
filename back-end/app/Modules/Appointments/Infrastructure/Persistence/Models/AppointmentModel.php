<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Numbering\DailyNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Appointments\Domain\Enums\AppointmentStatus;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property AppointmentStatus $status
 * @property \Illuminate\Support\Carbon $scheduled_at
 */
final class AppointmentModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'appointments';

    protected $casts = [
        'status' => AppointmentStatus::class,
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    protected function appointmentNumber(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => DailyNumber::present($value),
        );
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
     * @return HasMany<AppointmentStatusHistoryModel, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistoryModel::class, 'appointment_id');
    }

    public function endsAt(): \Illuminate\Support\Carbon
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes);
    }

    /**
     * @param  Builder<AppointmentModel>  $query
     * @return Builder<AppointmentModel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AppointmentStatus::Scheduled->value,
            AppointmentStatus::Confirmed->value,
            AppointmentStatus::CheckedIn->value,
        ]);
    }
}
