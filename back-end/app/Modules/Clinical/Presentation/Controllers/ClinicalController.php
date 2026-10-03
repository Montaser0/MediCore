<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Clinical\Application\Actions\AddClinicalNoteAction;
use App\Modules\Clinical\Application\Actions\AddDiagnosisAction;
use App\Modules\Clinical\Application\Actions\RecordProcedureAction;
use App\Modules\Clinical\Application\Actions\RecordVitalSignsAction;
use App\Modules\Clinical\Infrastructure\Persistence\Models\ClinicalNoteModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\ProcedureModel;
use App\Modules\Clinical\Infrastructure\Persistence\Models\VitalSignModel;
use App\Modules\Clinical\Presentation\Requests\StoreClinicalNoteRequest;
use App\Modules\Clinical\Presentation\Requests\StoreDiagnosisRequest;
use App\Modules\Clinical\Presentation\Requests\StoreProcedureRequest;
use App\Modules\Clinical\Presentation\Requests\StoreVitalSignsRequest;
use App\Modules\Clinical\Presentation\Resources\ClinicalNoteResource;
use App\Modules\Clinical\Presentation\Resources\DiagnosisResource;
use App\Modules\Clinical\Presentation\Resources\ProcedureResource;
use App\Modules\Clinical\Presentation\Resources\VitalSignResource;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use Illuminate\Http\JsonResponse;

/**
 * Everything a clinician records against a single visit. All of these live
 * under /api/encounters/{encounter}/... because clinical data never exists
 * outside a visit.
 */
final class ClinicalController extends ApiController
{
    public function vitals(EncounterModel $encounter): JsonResponse
    {
        $readings = VitalSignModel::query()
            ->with('recordedBy')
            ->where('encounter_id', $encounter->getKey())
            ->orderByDesc('recorded_at')
            ->get();

        return ApiResponse::data(VitalSignResource::collection($readings)->resolve());
    }

    public function storeVitals(StoreVitalSignsRequest $request, EncounterModel $encounter, RecordVitalSignsAction $action): JsonResponse
    {
        $reading = $action->execute($encounter->getKey(), $request->validated());

        return ApiResponse::created(new VitalSignResource($reading->load('recordedBy')));
    }

    public function notes(EncounterModel $encounter): JsonResponse
    {
        $notes = ClinicalNoteModel::query()
            ->with('author')
            ->where('encounter_id', $encounter->getKey())
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::data(ClinicalNoteResource::collection($notes)->resolve());
    }

    public function storeNote(StoreClinicalNoteRequest $request, EncounterModel $encounter, AddClinicalNoteAction $action): JsonResponse
    {
        return ApiResponse::created(new ClinicalNoteResource(
            $action->execute($encounter->getKey(), $request->validated())
        ));
    }

    public function diagnoses(EncounterModel $encounter): JsonResponse
    {
        $diagnoses = DiagnosisModel::query()
            ->with(['code', 'doctor.staff'])
            ->where('encounter_id', $encounter->getKey())
            ->orderByDesc('diagnosed_at')
            ->get();

        return ApiResponse::data(DiagnosisResource::collection($diagnoses)->resolve());
    }

    public function storeDiagnosis(StoreDiagnosisRequest $request, EncounterModel $encounter, AddDiagnosisAction $action): JsonResponse
    {
        return ApiResponse::created(new DiagnosisResource(
            $action->execute($encounter->getKey(), $request->validated())
        ));
    }

    public function procedures(EncounterModel $encounter): JsonResponse
    {
        $procedures = ProcedureModel::query()
            ->with('doctor.staff')
            ->where('encounter_id', $encounter->getKey())
            ->orderByDesc('performed_at')
            ->get();

        return ApiResponse::data(ProcedureResource::collection($procedures)->resolve());
    }

    public function storeProcedure(StoreProcedureRequest $request, EncounterModel $encounter, RecordProcedureAction $action): JsonResponse
    {
        return ApiResponse::created(new ProcedureResource(
            $action->execute($encounter->getKey(), $request->validated())
        ));
    }
}
