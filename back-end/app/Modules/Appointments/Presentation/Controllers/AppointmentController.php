<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Appointments\Application\Actions\ChangeAppointmentStatusAction;
use App\Modules\Appointments\Application\Actions\CheckInAppointmentAction;
use App\Modules\Appointments\Application\Actions\CreateAppointmentAction;
use App\Modules\Appointments\Application\Queries\ListAppointmentsQuery;
use App\Modules\Appointments\Domain\Enums\AppointmentStatus;
use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use App\Modules\Appointments\Presentation\Requests\CancelAppointmentRequest;
use App\Modules\Appointments\Presentation\Requests\StoreAppointmentRequest;
use App\Modules\Appointments\Presentation\Requests\UpdateAppointmentRequest;
use App\Modules\Appointments\Presentation\Resources\AppointmentResource;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppointmentController extends ApiController
{
    public function index(Request $request, ListAppointmentsQuery $query): JsonResponse
    {
        $appointments = $query->execute(
            array_filter($request->only([
                'patient_id', 'department_id', 'clinic_id', 'doctor_id',
                'status', 'date', 'from', 'to', 'active_only',
            ])),
            $this->perPage(),
        );

        return ApiResponse::paginated(AppointmentResource::collection($appointments), $appointments);
    }

    /**
     * Today's schedule, which is what the reception screen opens on.
     */
    public function today(Request $request, ListAppointmentsQuery $query): JsonResponse
    {
        $appointments = $query->execute(
            ['date' => now()->toDateString()] + array_filter($request->only(['department_id', 'clinic_id', 'doctor_id', 'status'])),
            $this->perPage(100),
        );

        return ApiResponse::paginated(AppointmentResource::collection($appointments), $appointments);
    }

    public function forPatient(PatientModel $patient, ListAppointmentsQuery $query): JsonResponse
    {
        $appointments = $query->execute(['patient_id' => $patient->getKey()], $this->perPage());

        return ApiResponse::paginated(AppointmentResource::collection($appointments), $appointments);
    }

    public function show(AppointmentModel $appointment): JsonResponse
    {
        return ApiResponse::resource(new AppointmentResource(
            $appointment->load(['patient', 'department', 'clinic', 'doctor.staff'])
        ));
    }

    public function store(StoreAppointmentRequest $request, CreateAppointmentAction $action, CurrentUser $currentUser): JsonResponse
    {
        abort_if($currentUser->ownDoctorId() !== null, 403, 'لا يمكنك إنشاء موعد جديد.');

        return ApiResponse::created(new AppointmentResource($action->execute($request->validated())));
    }

    public function update(UpdateAppointmentRequest $request, AppointmentModel $appointment, CurrentUser $currentUser): JsonResponse
    {
        $this->guardOwnAppointment($appointment, $currentUser);
        $data = $request->validated();
        $ownDoctorId = $currentUser->ownDoctorId();

        if ($ownDoctorId !== null) {
            $data['doctor_id'] = $ownDoctorId;
        }

        $appointment->fill($data)->save();

        return ApiResponse::resource(new AppointmentResource(
            $appointment->fresh(['patient', 'department', 'clinic', 'doctor.staff'])
        ));
    }

    public function confirm(AppointmentModel $appointment, ChangeAppointmentStatusAction $action, CurrentUser $currentUser): JsonResponse
    {
        $this->guardOwnAppointment($appointment, $currentUser);

        return ApiResponse::resource(new AppointmentResource(
            $action->execute($appointment, AppointmentStatus::Confirmed, 'تم تأكيد الموعد مع المريض')
        ));
    }

    public function checkIn(AppointmentModel $appointment, CheckInAppointmentAction $action, CurrentUser $currentUser): JsonResponse
    {
        $this->guardOwnAppointment($appointment, $currentUser);

        return ApiResponse::resource(new AppointmentResource($action->execute($appointment)));
    }

    public function cancel(CancelAppointmentRequest $request, AppointmentModel $appointment, ChangeAppointmentStatusAction $action, CurrentUser $currentUser): JsonResponse
    {
        $this->guardOwnAppointment($appointment, $currentUser);

        $reason = trim($request->string('reason')->toString());

        return ApiResponse::resource(new AppointmentResource(
            $action->execute($appointment, AppointmentStatus::Cancelled, $reason !== '' ? $reason : 'أُلغي الموعد')
        ));
    }

    public function markNoShow(AppointmentModel $appointment, ChangeAppointmentStatusAction $action, CurrentUser $currentUser): JsonResponse
    {
        $this->guardOwnAppointment($appointment, $currentUser);

        return ApiResponse::resource(new AppointmentResource(
            $action->execute($appointment, AppointmentStatus::NoShow, 'لم يحضر المريض')
        ));
    }

    public function statusHistory(AppointmentModel $appointment): JsonResponse
    {
        return ApiResponse::data(
            $appointment->statusHistory()->orderBy('created_at')->get()->map(fn ($entry) => [
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'note' => $entry->note,
                'changed_by' => $entry->changed_by,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->all()
        );
    }

    private function guardOwnAppointment(AppointmentModel $appointment, CurrentUser $currentUser): void
    {
        $doctorId = $currentUser->ownDoctorId();

        if ($doctorId === null) {
            return;
        }

        abort_unless((int) $appointment->doctor_id === $doctorId, 403, 'هذا الموعد ليس من مواعيدك.');
    }
}
