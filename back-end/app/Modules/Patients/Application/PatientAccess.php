<?php

declare(strict_types=1);

namespace App\Modules\Patients\Application;

use App\Core\Auth\CurrentUser;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;

/**
 * Keeps a doctor inside their own patients, and a clinic or wing data-entry
 * account inside the department they were given.
 */
final class PatientAccess
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    public function assertVisible(PatientModel $patient): void
    {
        $doctorId = $this->currentUser->ownDoctorId();

        if ($doctorId !== null) {
            $doctorClinics = $this->currentUser->doctorClinicIds();
            $allowed = PatientModel::query()
                ->whereKey($patient->getKey())
                ->where(function ($inner) use ($doctorId, $doctorClinics): void {
                    $inner->whereHas(
                        'encounters',
                        fn ($encounters) => $encounters->where('doctor_id', $doctorId)->where('status', '!=', 'cancelled'),
                    )->orWhereHas(
                        'appointments',
                        fn ($appointments) => $appointments->where('doctor_id', $doctorId)->where('status', '!=', 'cancelled'),
                    );
                    if ($doctorClinics !== []) {
                        $inner->orWhereHas(
                            'encounters',
                            fn ($encounters) => $encounters
                                ->whereNull('doctor_id')
                                ->whereIn('clinic_id', $doctorClinics)
                                ->where('status', '!=', 'cancelled'),
                        );
                    }
                })
                ->exists();

            abort_unless($allowed, 403, 'هذا المريض ليس من مرضاك.');
        }

        $clinicIds = $this->currentUser->dataEntryClinicIds();

        if ($clinicIds !== null) {
            $allowed = PatientModel::query()
                ->whereKey($patient->getKey())
                ->where(function ($inner) use ($clinicIds): void {
                    $inner->whereHas(
                        'encounters',
                        fn ($encounters) => $encounters->whereIn('clinic_id', $clinicIds)->where('status', '!=', 'cancelled'),
                    )->orWhereHas(
                        'appointments',
                        fn ($appointments) => $appointments->whereIn('clinic_id', $clinicIds)->where('status', '!=', 'cancelled'),
                    );
                })
                ->exists();

            abort_unless($allowed, 403, 'هذا المريض ليس من المرضى المحوّلين إلى عيادتك.');

            return;
        }

        $departmentIds = $this->currentUser->dataEntryDepartmentIds();

        if ($departmentIds === null) {
            return;
        }

        $allowed = PatientModel::query()
            ->whereKey($patient->getKey())
            ->where(function ($inner) use ($departmentIds): void {
                $inner->whereHas(
                    'encounters',
                    fn ($encounters) => $encounters->whereIn('department_id', $departmentIds)->where('status', '!=', 'cancelled'),
                )->orWhereHas(
                    'appointments',
                    fn ($appointments) => $appointments->whereIn('department_id', $departmentIds)->where('status', '!=', 'cancelled'),
                );
            })
            ->exists();

        abort_unless($allowed, 403, 'هذا المريض خارج الجناح المرتبط بحسابك.');
    }
}
