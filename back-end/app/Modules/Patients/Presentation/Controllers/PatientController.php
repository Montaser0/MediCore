<?php

declare(strict_types=1);

namespace App\Modules\Patients\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Authorization\Permissions;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Core\Support\Contracts\AuditRecorder;
use App\Modules\Patients\Application\Actions\RegisterPatientAction;
use App\Modules\Patients\Application\Actions\UpdatePatientAction;
use App\Modules\Patients\Application\DTOs\PatientData;
use App\Modules\Patients\Application\PatientAccess;
use App\Modules\Patients\Application\Queries\GetPatientFileQuery;
use App\Modules\Patients\Application\Queries\SearchPatientsQuery;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Patients\Presentation\Requests\StorePatientRequest;
use App\Modules\Patients\Presentation\Requests\UpdatePatientRequest;
use App\Modules\Patients\Presentation\Resources\PatientHeaderResource;
use App\Modules\Patients\Presentation\Resources\PatientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PatientController extends ApiController
{
    public function index(Request $request, SearchPatientsQuery $query): JsonResponse
    {
        $patients = $query->execute(
            $request->only([
                'search', 'status', 'gender', 'city', 'blood_type', 'registered_from', 'registered_to',
                'department_id', 'clinic_id',
            ]),
            $this->perPage(),
        );

        return ApiResponse::paginated(PatientResource::collection($patients), $patients);
    }

    public function show(Request $request, PatientModel $patient, AuditRecorder $audit, PatientAccess $access): JsonResponse
    {
        $access->assertVisible($patient);

        // Opening a medical file is itself an auditable event.
        $audit->record('patients.viewed', PatientModel::class, $patient->getKey());

        return ApiResponse::resource(
            new PatientResource($patient->load(['contacts', 'allergies', 'conditions']))
        );
    }

    /**
     * Compact header used by the doctor, lab, radiology and pharmacy screens.
     */
    public function header(PatientModel $patient, PatientAccess $access): JsonResponse
    {
        $access->assertVisible($patient);
        return ApiResponse::resource(new PatientHeaderResource(
            $patient->load([
                'allergies' => fn ($query) => $query->active(),
                'conditions' => fn ($query) => $query->active(),
                'contacts' => fn ($query) => $query->where('is_emergency_contact', true),
            ])
        ));
    }

    /**
     * The full medical record: identity plus one section per module.
     */
    public function file(Request $request, PatientModel $patient, GetPatientFileQuery $query, AuditRecorder $audit, PatientAccess $access): JsonResponse
    {
        $access->assertVisible($patient);

        $audit->record('patients.medical_record_viewed', PatientModel::class, $patient->getKey());

        $result = $query->execute($patient->getKey(), $request->integer('encounter_id') ?: null);

        return ApiResponse::data([
            'patient' => new PatientHeaderResource($result['patient']),
            'sections' => $result['sections'],
        ]);
    }

    public function store(StorePatientRequest $request, RegisterPatientAction $action, CurrentUser $currentUser): JsonResponse
    {
        abort_if($currentUser->isClinicalDesk(), 403, 'لا يمكنك إضافة مريض جديد.');

        $hospitalId = $currentUser->hospitalId()
            ?? throw BusinessRuleException::make('حسابك غير مرتبط بمستشفى. اطلب من المسؤول ربطه بملف موظف.');

        $patient = $action->execute(PatientData::fromArray($request->validated(), $hospitalId));

        return ApiResponse::created(new PatientResource($patient));
    }

    public function update(UpdatePatientRequest $request, PatientModel $patient, UpdatePatientAction $action, PatientAccess $access): JsonResponse
    {
        $access->assertVisible($patient);

        return ApiResponse::resource(new PatientResource($action->execute($patient, $request->validated())));
    }

    public function destroy(Request $request, PatientModel $patient): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::PATIENTS_DELETE) === true, 403);

        $patient->delete();

        return ApiResponse::noContent();
    }
}
