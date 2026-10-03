<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Application\Queries;

use App\Core\Auth\CurrentUser;
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

        if ($ownDoctorId !== null) {
            $filters['doctor_id'] = $ownDoctorId;
        }

        return AppointmentModel::query()
            ->with(['patient', 'department', 'clinic', 'doctor.staff'])
            ->forCurrentHospital()
            ->when($filters['patient_id'] ?? null, fn ($q, $value) => $q->where('patient_id', $value))
            ->when($filters['department_id'] ?? null, fn ($q, $value) => $q->where('department_id', $value))
            ->when($filters['clinic_id'] ?? null, fn ($q, $value) => $q->where('clinic_id', $value))
            ->when($filters['doctor_id'] ?? null, fn ($q, $value) => $q->where('doctor_id', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->whereIn('status', (array) $value))
            ->when($filters['date'] ?? null, fn ($q, $value) => $q->whereDate('scheduled_at', $value))
            ->when($filters['from'] ?? null, fn ($q, $value) => $q->where('scheduled_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($q, $value) => $q->where('scheduled_at', '<=', $value))
            ->when(
                ($filters['active_only'] ?? null) !== null && filter_var($filters['active_only'], FILTER_VALIDATE_BOOLEAN),
                fn ($q) => $q->active(),
            )
            ->orderByRaw("case when status in ('scheduled', 'confirmed') then 0 else 1 end")
            ->orderBy('scheduled_at')
            ->paginate($perPage);
    }
}
