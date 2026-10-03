<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Inpatient\Application\Actions\ChangeBedStatusAction;
use App\Modules\Inpatient\Application\Queries\BedOccupancyQuery;
use App\Modules\Inpatient\Domain\Enums\BedStatus;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;
use App\Modules\Inpatient\Presentation\Requests\StoreBedRequest;
use App\Modules\Inpatient\Presentation\Resources\BedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BedController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $beds = BedModel::query()
            ->with('room.ward')
            ->whereHas('room', fn ($query) => $query->active())
            ->inWard($request->filled('ward_id') ? $request->integer('ward_id') : null)
            ->when($request->filled('status'), fn ($query) => $query->whereIn('status', (array) $request->input('status')))
            ->when($request->boolean('available_only'), fn ($query) => $query->available())
            ->orderBy('room_id')
            ->orderBy('bed_number')
            ->paginate($this->perPage(100));

        return ApiResponse::paginated(BedResource::collection($beds), $beds);
    }

    /**
     * The ward board the nursing station keeps open all day.
     */
    public function occupancy(Request $request, BedOccupancyQuery $query): JsonResponse
    {
        return ApiResponse::data($query->execute(
            $request->filled('ward_id') ? $request->integer('ward_id') : null
        ));
    }

    public function store(StoreBedRequest $request): JsonResponse
    {
        return ApiResponse::created(new BedResource(
            BedModel::query()->create($request->validated() + ['status' => BedStatus::Available->value])->load('room.ward')
        ));
    }

    public function changeStatus(Request $request, BedModel $bed, ChangeBedStatusAction $action): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(BedStatus::values())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $action->execute($bed, BedStatus::from($validated['status']), $validated['notes'] ?? null);

        return ApiResponse::resource(new BedResource($updated->load('room.ward')));
    }
}
