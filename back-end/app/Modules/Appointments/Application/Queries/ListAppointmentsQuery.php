<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Application\Queries;

use App\Core\Auth\CurrentUser;
use App\Core\Persistence\QueryWindow;
use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListAppointmentsQuery
{
    public function __construct(private readonly CurrentUser $currentUser) {}
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $ownDoctorId = $this->currentUser->ownDoctorId();
        $clinicIds = $this->currentUser->dataEntryClinicIds();
        $departmentIds = $this->currentUser->dataEntryDepartmentIds();

        if ($ownDoctorId !== null) {
            $filters['doctor_id'] = $ownDoctorId;
        }

        $perPage = max(1, min($perPage, 100));

        return AppointmentModel::query()
            ->select([
                'appointments.id',
                'appointments.hospital_id',
                'appointments.patient_id',
                'appointments.appointment_number',
                'appointments.department_id',
                'appointments.clinic_id',
                'appointments.doctor_id',
                'appointments.scheduled_at',
                'appointments.duration_minutes',
                'appointments.status',
                'appointments.reason',
                'appointments.notes',
                'appointments.encounter_id',
                'appointments.created_at',
            ])
            ->with([
                'patient:id,mrn,first_name,father_name,grandfather_name,last_name,phone,gender,date_of_birth,status',
                'department:id,name',
                'clinic:id,name',
                'doctor:id,staff_id',
                'doctor.staff:id,first_name,last_name',
            ])
            ->forCurrentHospital()
            ->when($filters['patient_id'] ?? null, fn ($q, $value) => $q->where('patient_id', $value))
            ->when($clinicIds !== null, fn ($q) => $q->whereIn('clinic_id', $clinicIds))
            ->when($departmentIds !== null, fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->when($filters['department_id'] ?? null, fn ($q, $value) => $q->where('department_id', $value))
            ->when($filters['clinic_id'] ?? null, fn ($q, $value) => $q->where('clinic_id', $value))
            ->when($filters['doctor_id'] ?? null, fn ($q, $value) => $q->where('doctor_id', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->whereIn('status', (array) $value))
            ->when($filters['date'] ?? null, fn ($q, $value) => QueryWindow::on($q, 'appointments.scheduled_at', $value))
            ->when($filters['from'] ?? null, fn ($q, $value) => QueryWindow::from($q, 'appointments.scheduled_at', $value))
            ->when($filters['to'] ?? null, fn ($q, $value) => QueryWindow::until($q, 'appointments.scheduled_at', $value))
            ->when(
                ($filters['active_only'] ?? null) !== null && filter_var($filters['active_only'], FILTER_VALIDATE_BOOLEAN),
                fn ($q) => $q->active(),
            )
            ->orderByRaw("case when status in ('scheduled', 'confirmed') then 0 else 1 end")
            ->orderBy('scheduled_at')
            ->paginate($perPage);
    }
}
