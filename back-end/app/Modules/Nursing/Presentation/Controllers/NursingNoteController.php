<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Nursing\Application\Actions\AddNursingNoteAction;
use App\Modules\Nursing\Domain\Enums\CarePlanStatus;
use App\Modules\Nursing\Infrastructure\Persistence\Models\CarePlanModel;
use App\Modules\Nursing\Infrastructure\Persistence\Models\NursingNoteModel;
use App\Modules\Nursing\Presentation\Requests\StoreCarePlanRequest;
use App\Modules\Nursing\Presentation\Requests\StoreNursingNoteRequest;
use App\Modules\Nursing\Presentation\Resources\CarePlanResource;
use App\Modules\Nursing\Presentation\Resources\NursingNoteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class NursingNoteController extends ApiController
{
    public function index(Request $request, EncounterModel $encounter): JsonResponse
    {
        $notes = NursingNoteModel::query()
            ->with('author')
            ->where('encounter_id', $encounter->getKey())
            ->when($request->filled('shift'), fn ($query) => $query->where('shift', $request->string('shift')))
            ->when($request->filled('note_type'), fn ($query) => $query->where('note_type', $request->string('note_type')))
            ->orderByDesc('created_at')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(NursingNoteResource::collection($notes), $notes);
    }

    public function store(StoreNursingNoteRequest $request, EncounterModel $encounter, AddNursingNoteAction $action): JsonResponse
    {
        return ApiResponse::created(new NursingNoteResource(
            $action->execute($encounter->getKey(), $request->validated())
        ));
    }

    public function carePlans(EncounterModel $encounter): JsonResponse
    {
        $plans = CarePlanModel::query()
            ->with('author')
            ->where('encounter_id', $encounter->getKey())
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::data(CarePlanResource::collection($plans)->resolve());
    }

    public function storeCarePlan(StoreCarePlanRequest $request, EncounterModel $encounter, CurrentUser $currentUser): JsonResponse
    {
        $plan = CarePlanModel::query()->create($request->validated() + [
            'hospital_id' => $encounter->hospital_id,
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->getKey(),
            'status' => CarePlanStatus::Active->value,
            'start_date' => $request->input('start_date', now()->toDateString()),
            'created_by' => $currentUser->id(),
        ]);

        return ApiResponse::created(new CarePlanResource($plan->load('author')));
    }

    /**
     * A care plan is evaluated rather than rewritten, so only the outcome
     * fields can change once it exists.
     */
    public function evaluateCarePlan(Request $request, CarePlanModel $carePlan): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(CarePlanStatus::values())],
            'evaluation' => ['required', 'string'],
        ]);

        $status = CarePlanStatus::from($validated['status']);

        $carePlan->forceFill([
            'status' => $status->value,
            'evaluation' => $validated['evaluation'],
            'resolved_date' => $status === CarePlanStatus::Active ? null : now()->toDateString(),
        ])->save();

        return ApiResponse::resource(new CarePlanResource($carePlan->load('author')));
    }
}
