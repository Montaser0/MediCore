<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Hospital\Application\Actions\CreateStaffAction;
use App\Modules\Hospital\Application\Actions\UpdateStaffAction;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use App\Modules\Hospital\Presentation\Requests\StoreStaffRequest;
use App\Modules\Hospital\Presentation\Requests\UpdateStaffRequest;
use App\Modules\Hospital\Presentation\Resources\StaffResource;
use App\Modules\Identity\Application\Actions\CreateUserAction;
use App\Modules\Identity\Application\DTOs\UserData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class StaffController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $staff = StaffModel::query()
            ->with(['department', 'clinic', 'doctor', 'user.roles'])
            ->forCurrentHospital()
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when(! $request->boolean('include_inactive') && ! $request->filled('is_active'), fn ($query) => $query->where('is_active', true))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = (string) $request->string('search');

                $query->where(fn ($inner) => $inner
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%"));
            })
            ->orderBy('last_name')
            ->paginate($this->perPage());

        return ApiResponse::paginated(StaffResource::collection($staff), $staff);
    }

    public function show(StaffModel $staff): JsonResponse
    {
        return ApiResponse::resource(new StaffResource($staff->load(['department', 'clinic', 'doctor', 'user.roles'])));
    }

    public function store(StoreStaffRequest $request, CreateStaffAction $action, CreateUserAction $users): JsonResponse
    {
        $staff = DB::transaction(function () use ($request, $action, $users): StaffModel {
            $data = $request->safe()->except(['username', 'password', 'email', 'role']);
            $role = $this->accountRole($request);

            if ($role !== null && empty($data['job_title'])) {
                $data['job_title'] = Permissions::staffRoles()[$role];
            }

            $data['is_doctor'] = $role === 'doctor';

            $staff = $action->execute($data);

            if ($role !== null) {
                $users->execute(UserData::fromArray([
                    'username' => $request->string('username')->toString(),
                    'email' => $request->input('email'),
                    'password' => $request->string('password')->toString(),
                    'staff_id' => $staff->getKey(),
                    'roles' => [$role],
                    'department_ids' => array_values(array_filter([$staff->department_id])),
                ]));
            }

            return $staff->fresh(['department', 'clinic', 'doctor', 'user.roles']);
        });

        return ApiResponse::created(new StaffResource($staff));
    }

    public function update(UpdateStaffRequest $request, StaffModel $staff, UpdateStaffAction $action): JsonResponse
    {
        return ApiResponse::resource(new StaffResource($action->execute($staff, $request->validated())));
    }

    public function destroy(Request $request, StaffModel $staff): JsonResponse
    {
        DeactivateRecord::run($request, $staff, Permissions::STAFF_MANAGE);

        return ApiResponse::noContent();
    }

    private function accountRole(Request $request): ?string
    {
        $role = $request->filled('role') ? $request->string('role')->toString() : null;

        if ($role === null && $request->boolean('is_doctor')) {
            return 'doctor';
        }

        return $role;
    }
}
