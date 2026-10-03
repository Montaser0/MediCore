<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Inpatient\Application\Actions\AdmitPatientAction;
use App\Modules\Inpatient\Application\Actions\DischargePatientAction;
use App\Modules\Inpatient\Application\Actions\TransferPatientAction;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use App\Modules\Inpatient\Presentation\Requests\AdmitPatientRequest;
use App\Modules\Inpatient\Presentation\Requests\DischargePatientRequest;
use App\Modules\Inpatient\Presentation\Requests\TransferPatientRequest;
use App\Modules\Inpatient\Presentation\Resources\AdmissionResource;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdmissionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $admissions = AdmissionModel::query()
            ->with(['patient', 'department', 'attendingDoctor', 'currentBedAssignment.bed.room.ward'])
            ->forCurrentHospital()
            ->when($request->boolean('active_only', true), fn ($query) => $query->active())
            ->when($request->filled('status'), fn ($query) => $query->whereIn('status', (array) $request->input('status')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('attending_doctor_id'), fn ($query) => $query->where('attending_doctor_id', $request->integer('attending_doctor_id')))
            ->when($request->filled('patient_id'), fn ($query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('ward_id'), fn ($query) => $query->whereHas(
                'currentBedAssignment.bed.room',
                fn ($room) => $room->where('ward_id', $request->integer('ward_id')),
            ))
            ->orderByDesc('admitted_at')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(AdmissionResource::collection($admissions), $admissions);
    }

    public function show(AdmissionModel $admission): JsonResponse
    {
        return ApiResponse::resource(new AdmissionResource($admission->load([
            'patient', 'department', 'attendingDoctor', 'admittedBy',
            'currentBedAssignment.bed.room.ward', 'bedAssignments.bed.room.ward',
        ])));
    }

    public function store(AdmitPatientRequest $request, AdmitPatientAction $action): JsonResponse
    {
        $patient = PatientModel::query()->findOrFail($request->integer('patient_id'));

        return ApiResponse::created(new AdmissionResource(
            $action->execute($patient, $request->validated())
        ));
    }

    public function transfer(TransferPatientRequest $request, AdmissionModel $admission, TransferPatientAction $action): JsonResponse
    {
        return ApiResponse::resource(new AdmissionResource(
            $action->execute($admission, $request->validated())->load('patient')
        ));
    }

    public function discharge(DischargePatientRequest $request, AdmissionModel $admission, DischargePatientAction $action): JsonResponse
    {
        return ApiResponse::resource(new AdmissionResource(
            $action->execute($admission, $request->validated())
        ));
    }
}
