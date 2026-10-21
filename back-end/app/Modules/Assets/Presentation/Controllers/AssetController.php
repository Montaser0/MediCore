<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Assets\Application\Actions\ChangeAssetCustodyAction;
use App\Modules\Assets\Application\Actions\RegisterAssetAction;
use App\Modules\Assets\Domain\Enums\AssetCategory;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Exceptions\InvalidAssetTransitionException;
use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetModel;
use App\Modules\Assets\Presentation\Requests\AssetNoteRequest;
use App\Modules\Assets\Presentation\Requests\AssignAssetRequest;
use App\Modules\Assets\Presentation\Requests\StoreAssetRequest;
use App\Modules\Assets\Presentation\Requests\UpdateAssetRequest;
use App\Modules\Assets\Presentation\Resources\AssetMovementResource;
use App\Modules\Assets\Presentation\Resources\AssetResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $assets = ItAssetModel::query()
            ->with('department')
            ->forCurrentHospital()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->search($request->string('search')->toString())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->orderByDesc('id')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(AssetResource::collection($assets), $assets);
    }

    public function summary(): JsonResponse
    {
        $base = ItAssetModel::query()->forCurrentHospital()->active();
        $counts = ['total' => 0];

        foreach (AssetStatus::cases() as $status) {
            $count = (clone $base)->where('status', $status->value)->count();
            $counts[$status->value] = $count;
            $counts['total'] += $count;
        }

        return ApiResponse::data($counts);
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::data(AssetCategory::options());
    }

    public function statuses(): JsonResponse
    {
        return ApiResponse::data(AssetStatus::options());
    }

    public function show(ItAssetModel $asset): JsonResponse
    {
        return ApiResponse::resource(new AssetResource($this->owned($asset)->load('department')));
    }

    public function store(StoreAssetRequest $request, RegisterAssetAction $action): JsonResponse
    {
        $asset = $action->execute($request->validated(), $request->actorId());

        return ApiResponse::created(new AssetResource($asset));
    }

    public function update(UpdateAssetRequest $request, ItAssetModel $asset): JsonResponse
    {
        $asset = $this->owned($asset);
        $attributes = $request->validated();

        if ($asset->status !== AssetStatus::InStock) {
            $departmentChanged = array_key_exists('department_id', $attributes)
                && (int) ($attributes['department_id'] ?? 0) !== (int) ($asset->department_id ?? 0);
            $locationChanged = array_key_exists('location', $attributes)
                && (string) ($attributes['location'] ?? '') !== (string) ($asset->location ?? '');

            if ($departmentChanged || $locationChanged) {
                throw InvalidAssetTransitionException::because('غيّر القسم أو المكان من إجراء التسليم أو الإرجاع.');
            }

            unset($attributes['department_id'], $attributes['location']);
        }

        if (array_key_exists('received_by', $attributes)) {
            $holder = filled($attributes['received_by'] ?? null) ? trim((string) $attributes['received_by']) : null;
            $attributes['received_by'] = $holder;

            if (in_array($asset->status, [AssetStatus::InStock, AssetStatus::Assigned], true)) {
                $attributes['status'] = $holder === null ? AssetStatus::InStock : AssetStatus::Assigned;
            }
        }

        $asset->update($attributes);

        return ApiResponse::resource(new AssetResource($asset->load('department')));
    }

    public function destroy(Request $request, ItAssetModel $asset): JsonResponse
    {
        $this->owned($asset);
        DeactivateRecord::run($request, $asset, Permissions::ASSETS_MANAGE);

        return ApiResponse::noContent();
    }

    public function movements(ItAssetModel $asset): JsonResponse
    {
        $asset = $this->owned($asset);
        $movements = $asset->movements()
            ->with(['actor.staff', 'fromDepartment', 'toDepartment'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::data(AssetMovementResource::collection($movements)->resolve());
    }

    public function assign(AssignAssetRequest $request, ItAssetModel $asset, ChangeAssetCustodyAction $action): JsonResponse
    {
        $asset = $action->assign(
            $this->owned($asset),
            $request->input('received_by'),
            $request->filled('department_id') ? $request->integer('department_id') : null,
            $request->input('location'),
            $request->input('note'),
            $request->actorId(),
        );

        return ApiResponse::resource(new AssetResource($asset));
    }

    public function returnToStock(AssetNoteRequest $request, ItAssetModel $asset, ChangeAssetCustodyAction $action): JsonResponse
    {
        $asset = $action->returnToStock(
            $this->owned($asset),
            $request->filled('department_id') ? $request->integer('department_id') : null,
            $request->input('location'),
            $request->input('note'),
            $request->actorId(),
        );

        return ApiResponse::resource(new AssetResource($asset));
    }

    public function maintenance(AssetNoteRequest $request, ItAssetModel $asset, ChangeAssetCustodyAction $action): JsonResponse
    {
        $asset = $action->sendToMaintenance($this->owned($asset), $request->input('note'), $request->actorId());

        return ApiResponse::resource(new AssetResource($asset));
    }

    public function repair(AssetNoteRequest $request, ItAssetModel $asset, ChangeAssetCustodyAction $action): JsonResponse
    {
        $asset = $action->markRepaired($this->owned($asset), $request->input('note'), $request->actorId());

        return ApiResponse::resource(new AssetResource($asset));
    }

    public function retire(AssetNoteRequest $request, ItAssetModel $asset, ChangeAssetCustodyAction $action): JsonResponse
    {
        $asset = $action->retire($this->owned($asset), $request->input('note'), $request->actorId());

        return ApiResponse::resource(new AssetResource($asset));
    }

    private function owned(ItAssetModel $asset): ItAssetModel
    {
        $hospitalId = app(CurrentUser::class)->hospitalId();
        abort_if($hospitalId !== null && (int) $asset->hospital_id !== $hospitalId, 404);

        return $asset;
    }
}
