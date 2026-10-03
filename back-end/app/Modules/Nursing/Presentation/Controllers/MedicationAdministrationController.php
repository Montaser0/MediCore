<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Nursing\Application\Actions\RecordMedicationAdministrationAction;
use App\Modules\Nursing\Application\Actions\ScheduleMedicationAdministrationAction;
use App\Modules\Nursing\Application\Queries\NursingWorklistQuery;
use App\Modules\Nursing\Infrastructure\Persistence\Models\MedicationAdministrationModel;
use App\Modules\Nursing\Presentation\Requests\RecordAdministrationRequest;
use App\Modules\Nursing\Presentation\Resources\MedicationAdministrationResource;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionItemModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MedicationAdministrationController extends ApiController
{
    /**
     * The medication administration record for one visit.
     */
    public function index(Request $request, EncounterModel $encounter): JsonResponse
    {
        $doses = MedicationAdministrationModel::query()
            ->with(['medication', 'administeredBy'])
            ->where('encounter_id', $encounter->getKey())
            ->when($request->filled('status'), fn ($query) => $query->whereIn('status', (array) $request->input('status')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('scheduled_at', $request->date('date')))
            ->orderBy('scheduled_at')
            ->get();

        return ApiResponse::data(MedicationAdministrationResource::collection($doses)->resolve());
    }

    public function worklist(Request $request, NursingWorklistQuery $query): JsonResponse
    {
        return ApiResponse::data($query->execute(
            $request->filled('ward_id') ? $request->integer('ward_id') : null,
            $request->filled('until') ? $request->date('until') : null,
        ));
    }

    public function schedule(Request $request, PrescriptionItemModel $item, ScheduleMedicationAdministrationAction $action): JsonResponse
    {
        $validated = $request->validate([
            'times' => ['required', 'array', 'min:1'],
            'times.*' => ['required', 'date_format:H:i'],
            'days' => ['required', 'integer', 'min:1', 'max:60'],
            'starting_from' => ['nullable', 'date'],
        ]);

        $doses = $action->execute(
            $item,
            $validated['times'],
            (int) $validated['days'],
            isset($validated['starting_from']) ? $request->date('starting_from') : null,
        );

        return ApiResponse::data(
            MedicationAdministrationResource::collection(collect($doses))->resolve(),
            ['scheduled' => count($doses)],
            201,
        );
    }

    public function record(
        RecordAdministrationRequest $request,
        MedicationAdministrationModel $administration,
        RecordMedicationAdministrationAction $action,
    ): JsonResponse {
        return ApiResponse::resource(new MedicationAdministrationResource(
            $action->execute($administration, $request->validated())->load(['medication', 'administeredBy'])
        ));
    }
}
