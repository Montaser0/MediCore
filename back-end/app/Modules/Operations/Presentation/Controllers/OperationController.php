<?php

declare(strict_types=1);

namespace App\Modules\Operations\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Operations\Application\Actions\ChangeOperationStatusAction;
use App\Modules\Operations\Application\Actions\CompleteOperationAction;
use App\Modules\Operations\Application\Actions\RecordOperationMaterialsAction;
use App\Modules\Operations\Application\Actions\ScheduleOperationAction;
use App\Modules\Operations\Domain\Enums\OperationNoteType;
use App\Modules\Operations\Domain\Enums\OperationStatus;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel;
use App\Modules\Operations\Presentation\Requests\CompleteOperationRequest;
use App\Modules\Operations\Presentation\Requests\ScheduleOperationRequest;
use App\Modules\Operations\Presentation\Resources\OperationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OperationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $operations = OperationModel::query()
            ->with(['type', 'theatre', 'patient', 'team.user'])
            ->forCurrentHospital()
            ->when($request->boolean('open_only'), fn ($query) => $query->open())
            ->when($request->filled('status'), fn ($query) => $query->whereIn('status', (array) $request->input('status')))
            ->when($request->filled('theatre_id'), fn ($query) => $query->where('theatre_id', $request->integer('theatre_id')))
            ->when($request->filled('patient_id'), fn ($query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('scheduled_at', $request->date('date')))
            ->when($request->filled('from'), fn ($query) => $query->where('scheduled_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('scheduled_at', '<=', $request->date('to')))
            ->when($request->filled('surgeon_id'), fn ($query) => $query->whereHas(
                'team',
                fn ($team) => $team->where('user_id', $request->integer('surgeon_id')),
            ))
            ->orderBy('scheduled_at')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(OperationResource::collection($operations), $operations);
    }

    public function show(OperationModel $operation): JsonResponse
    {
        return ApiResponse::resource(new OperationResource($operation->load([
            'type', 'theatre', 'patient', 'team.user', 'notes.author', 'materials.item',
        ])));
    }

    public function store(ScheduleOperationRequest $request, ScheduleOperationAction $action): JsonResponse
    {
        return ApiResponse::created(new OperationResource($action->execute($request->validated())));
    }

    /**
     * Consent is recorded separately from scheduling because it is obtained
     * from the patient, often on a different day.
     */
    public function recordConsent(Request $request, OperationModel $operation): JsonResponse
    {
        $request->validate(['obtained' => ['required', 'boolean']]);

        $obtained = $request->boolean('obtained');

        $operation->forceFill([
            'consent_obtained' => $obtained,
            'consent_obtained_at' => $obtained ? now() : null,
        ])->save();

        return ApiResponse::resource(new OperationResource($operation->load('type')));
    }

    public function changeStatus(Request $request, OperationModel $operation, ChangeOperationStatusAction $action): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(OperationStatus::values())],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $action->execute(
            $operation,
            OperationStatus::from($validated['status']),
            $validated['reason'] ?? null,
        );

        return ApiResponse::resource(new OperationResource($updated->load(['type', 'team.user'])));
    }

    public function complete(CompleteOperationRequest $request, OperationModel $operation, CompleteOperationAction $action): JsonResponse
    {
        return ApiResponse::resource(new OperationResource($action->execute($operation, $request->validated())));
    }

    public function addNote(Request $request, OperationModel $operation, CurrentUser $currentUser): JsonResponse
    {
        $validated = $request->validate([
            'note_type' => ['required', Rule::in(OperationNoteType::values())],
            'content' => ['required', 'string'],
        ]);

        $operation->notes()->create($validated + ['created_by' => $currentUser->id()]);

        return ApiResponse::resource(new OperationResource($operation->load('notes.author')));
    }

    public function recordMaterials(Request $request, OperationModel $operation, RecordOperationMaterialsAction $action): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'materials' => ['required', 'array', 'min:1'],
            'materials.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'materials.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $updated = $action->execute($operation, (int) $validated['warehouse_id'], $validated['materials']);

        return ApiResponse::resource(new OperationResource($updated->load('materials.item')));
    }
}
