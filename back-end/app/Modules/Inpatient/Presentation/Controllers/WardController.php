<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Inpatient\Application\Actions\CreateRoomWithBedsAction;
use App\Modules\Inpatient\Application\Actions\DeleteRoomAction;
use App\Modules\Inpatient\Application\Actions\UpdateRoomAction;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\RoomModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\WardModel;
use App\Modules\Inpatient\Presentation\Requests\StoreWardRequest;
use App\Modules\Inpatient\Presentation\Resources\WardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WardController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $wards = WardModel::query()
            ->with(['department', 'rooms' => fn ($query) => $query->active()])
            ->withCount('beds')
            ->forCurrentHospital()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->orderBy('name')
            ->get();

        return ApiResponse::data(WardResource::collection($wards)->resolve());
    }

    public function store(StoreWardRequest $request, CurrentUser $currentUser): JsonResponse
    {
        $ward = WardModel::query()->create($request->validated() + [
            'hospital_id' => $currentUser->hospitalId(),
        ]);

        return ApiResponse::created(new WardResource($ward->load('department')));
    }

    public function update(StoreWardRequest $request, WardModel $ward): JsonResponse
    {
        $ward->fill($request->validated())->save();

        return ApiResponse::resource(new WardResource($ward->load('department')));
    }

    public function destroy(Request $request, WardModel $ward): JsonResponse
    {
        DeactivateRecord::run($request, $ward, Permissions::INPATIENT_BEDS_MANAGE);

        return ApiResponse::noContent();
    }

    public function storeRoom(Request $request, WardModel $ward, CreateRoomWithBedsAction $action): JsonResponse
    {
        $validated = $request->validate([
            'room_number' => ['required', 'string', 'max:50'],
            'room_type' => ['nullable', 'string', 'max:50'],
            'bed_count' => ['required_without:capacity', 'integer', 'min:1', 'max:20'],
            'capacity' => ['required_without:bed_count', 'integer', 'min:1', 'max:20'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
        ], [
            'room_number.required' => 'رقم الغرفة مطلوب.',
            'bed_count.required_without' => 'حدد عدد الأسرة في الغرفة.',
            'bed_count.min' => 'الغرفة تحتاج سريراً واحداً على الأقل.',
            'bed_count.max' => 'عدد الأسرة في الغرفة لا يتجاوز 20.',
            'capacity.required_without' => 'حدد عدد الأسرة في الغرفة.',
            'capacity.min' => 'الغرفة تحتاج سريراً واحداً على الأقل.',
            'capacity.max' => 'عدد الأسرة في الغرفة لا يتجاوز 20.',
        ]);

        $room = $action->execute($ward, $validated);

        return ApiResponse::data([
            'id' => $room->getKey(),
            'room_number' => $room->room_number,
            'room_type' => $room->room_type,
            'capacity' => $room->capacity,
            'bed_count' => $room->beds->count(),
            'daily_rate' => $room->effectiveDailyRate(),
            'ward_id' => $ward->getKey(),
            'beds' => $room->beds->map(fn ($bed) => [
                'id' => $bed->getKey(),
                'bed_number' => $bed->bed_number,
                'status' => $bed->status->value,
                'status_label' => $bed->status->label(),
            ])->values()->all(),
        ], status: 201);
    }

    public function updateRoom(Request $request, RoomModel $room, UpdateRoomAction $action): JsonResponse
    {
        $validated = $request->validate([
            'ward_id' => ['required', 'integer', 'exists:wards,id'],
            'room_number' => ['required', 'string', 'max:50'],
            'room_type' => ['nullable', 'string', 'max:50'],
            'bed_count' => ['required', 'integer', 'min:1', 'max:20'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
        ], [
            'room_number.required' => 'رقم الغرفة مطلوب.',
            'bed_count.required' => 'حدد عدد الأسرة في الغرفة.',
            'bed_count.min' => 'الغرفة تحتاج سريراً واحداً على الأقل.',
            'bed_count.max' => 'عدد الأسرة في الغرفة لا يتجاوز 20.',
        ]);

        $updated = $action->execute($room, $validated);

        return ApiResponse::data([
            'id' => $updated->getKey(),
            'room_number' => $updated->room_number,
            'room_type' => $updated->room_type,
            'capacity' => $updated->capacity,
            'bed_count' => $updated->beds->count(),
            'daily_rate' => $updated->daily_rate,
            'ward_id' => $updated->ward_id,
        ]);
    }

    public function destroyRoom(RoomModel $room, DeleteRoomAction $action): JsonResponse
    {
        $action->execute($room);

        return ApiResponse::noContent();
    }
}
