<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Encounters\Application\Actions\CloseEncounterAction;
use App\Modules\Encounters\Application\Actions\CreateEncounterAction;
use App\Modules\Encounters\Application\Actions\StartEncounterAction;
use App\Modules\Encounters\Application\Actions\TransferEncounterAction;
use App\Modules\Encounters\Application\Queries\ListEncountersQuery;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Encounters\Presentation\Requests\CloseEncounterRequest;
use App\Modules\Encounters\Presentation\Requests\StoreEncounterRequest;
use App\Modules\Encounters\Presentation\Requests\TransferEncounterRequest;
use App\Modules\Encounters\Presentation\Resources\EncounterResource;
use App\Modules\Encounters\Presentation\Resources\EncounterTransferResource;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EncounterController extends ApiController
{
    public function index(Request $request, ListEncountersQuery $query, CurrentUser $currentUser): JsonResponse
    {
        $filters = array_filter($request->only([
            'patient_id', 'department_id', 'clinic_id', 'doctor_id',
            'status', 'type', 'open_only', 'date', 'from', 'to', 'search',
        ]));

        $ownDoctorId = $currentUser->ownDoctorId();
        if ($ownDoctorId !== null) {
            $filters['doctor_id'] = $ownDoctorId;
            $filters['shared_clinic_ids'] = $currentUser->doctorClinicIds();
        }

        $clinicIds = $currentUser->dataEntryClinicIds();
        $departmentIds = $currentUser->dataEntryDepartmentIds();

        if ($clinicIds !== null) {
            $filters['clinic_ids'] = $clinicIds;
        } elseif ($departmentIds !== null) {
            $filters['department_ids'] = $departmentIds;
        }

        $encounters = $query->execute($filters, $this->perPage());

        return ApiResponse::paginated(EncounterResource::collection($encounters), $encounters);
    }

    public function show(EncounterModel $encounter, CurrentUser $currentUser): JsonResponse
    {
        abort_unless(
            $currentUser->canAccessVisit(
                $encounter->department_id === null ? null : (int) $encounter->department_id,
                $encounter->clinic_id === null ? null : (int) $encounter->clinic_id,
            ),
            403,
            'هذه الزيارة ليست من المرضى المحوّلين إلى عيادتك.',
        );

        return ApiResponse::resource(new EncounterResource(
            $encounter->load(['patient.allergies', 'department', 'clinic', 'doctor.staff'])
        ));
    }

    /**
     * The clinic waiting list (PRD §22): who is queued for this department,
     * clinic or doctor, oldest first.
     */
    public function queue(Request $request, CurrentUser $currentUser): JsonResponse
    {
        $ownDoctorId = $currentUser->ownDoctorId();
        $doctorClinics = $ownDoctorId !== null ? $currentUser->doctorClinicIds() : [];
        $clinicIds = $currentUser->dataEntryClinicIds();
        $departmentIds = $currentUser->dataEntryDepartmentIds();
        $lockedClinic = $clinicIds !== null ? ($clinicIds[0] ?? null) : null;

        $encounters = EncounterModel::query()
            ->with(['patient', 'department', 'clinic', 'doctor.staff'])
            ->forCurrentHospital()
            ->queueFor(
                $request->integer('department_id') ?: null,
                $lockedClinic ?? ($request->integer('clinic_id') ?: null),
                $ownDoctorId !== null ? null : ($request->integer('doctor_id') ?: null),
            )
            ->when($ownDoctorId !== null, function ($query) use ($ownDoctorId, $doctorClinics): void {
                $query->where(function ($inner) use ($ownDoctorId, $doctorClinics): void {
                    $inner->where('doctor_id', $ownDoctorId);
                    if ($doctorClinics !== []) {
                        $inner->orWhere(function ($shared) use ($doctorClinics): void {
                            $shared->whereNull('doctor_id')->whereIn('clinic_id', $doctorClinics);
                        });
                    }
                });
            })
            ->when($clinicIds !== null, fn ($query) => $query->whereIn('clinic_id', $clinicIds))
            ->when($departmentIds !== null, fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('created_at', $request->string('date')->toString()))
            ->paginate($this->perPage(100));

        return ApiResponse::paginated(EncounterResource::collection($encounters), $encounters);
    }

    public function forPatient(Request $request, PatientModel $patient, ListEncountersQuery $query, CurrentUser $currentUser): JsonResponse
    {
        $filters = ['patient_id' => $patient->getKey()] + array_filter($request->only(['status', 'type', 'open_only']));
        $clinicIds = $currentUser->dataEntryClinicIds();
        $departmentIds = $currentUser->dataEntryDepartmentIds();

        if ($clinicIds !== null) {
            $filters['clinic_ids'] = $clinicIds;
        } elseif ($departmentIds !== null) {
            $filters['department_ids'] = $departmentIds;
        }

        $encounters = $query->execute($filters, $this->perPage());

        return ApiResponse::paginated(EncounterResource::collection($encounters), $encounters);
    }

    public function store(StoreEncounterRequest $request, CreateEncounterAction $action, CurrentUser $currentUser): JsonResponse
    {
        abort_if($currentUser->isClinicalDesk(), 403, 'لا يمكنك فتح زيارة جديدة.');

        $patient = PatientModel::query()->findOrFail($request->integer('patient_id'));

        return ApiResponse::created(new EncounterResource($action->execute($patient, $request->validated())));
    }

    public function start(Request $request, EncounterModel $encounter, StartEncounterAction $action): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
        ]);

        return ApiResponse::resource(new EncounterResource(
            $action->execute($encounter, $validated['doctor_id'] ?? null)
        ));
    }

    public function transfer(TransferEncounterRequest $request, EncounterModel $encounter, TransferEncounterAction $action): JsonResponse
    {
        return ApiResponse::created(new EncounterTransferResource($action->execute($encounter, $request->validated())));
    }

    public function transfers(EncounterModel $encounter): JsonResponse
    {
        return ApiResponse::data(EncounterTransferResource::collection(
            $encounter->transfers()->with(['fromDepartment', 'toDepartment'])->latest()->get()
        )->resolve());
    }

    public function close(CloseEncounterRequest $request, EncounterModel $encounter, CloseEncounterAction $action): JsonResponse
    {
        return ApiResponse::resource(new EncounterResource($action->execute($encounter, $request->validated())));
    }

    public function statusHistory(EncounterModel $encounter): JsonResponse
    {
        return ApiResponse::data(
            $encounter->statusHistory()->orderBy('created_at')->get()->map(fn ($entry) => [
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'note' => $entry->note,
                'changed_by' => $entry->changed_by,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->all()
        );
    }
}
