<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Identity\Application\Actions\CreateUserAction;
use App\Modules\Identity\Application\Actions\DisableUserAction;
use App\Modules\Identity\Application\Actions\ResetPasswordAction;
use App\Modules\Identity\Application\Actions\UpdateUserAction;
use App\Modules\Identity\Application\DTOs\UserData;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Identity\Presentation\Requests\CreateUserRequest;
use App\Modules\Identity\Presentation\Requests\ResetUserPasswordRequest;
use App\Modules\Identity\Presentation\Requests\UpdateUserRequest;
use App\Modules\Identity\Presentation\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permissions::USERS_MANAGE);

        $users = UserModel::query()
            ->with(['staff.department', 'roles'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = (string) $request->string('search');

                $query->where(function ($inner) use ($search): void {
                    $inner->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('staff', fn ($staff) => $staff
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->role((string) $request->string('role')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when(! $request->boolean('include_inactive') && ! $request->filled('is_active'), fn ($query) => $query->where('is_active', true))
            ->orderBy('username')
            ->paginate($this->perPage());

        return ApiResponse::paginated(UserResource::collection($users), $users);
    }

    public function show(Request $request, UserModel $user): JsonResponse
    {
        $this->authorizePermission($request, Permissions::USERS_MANAGE);

        return ApiResponse::resource(
            new UserResource($user->load(['staff.department', 'staff.doctor', 'roles', 'departments']))
        );
    }

    public function store(CreateUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $user = $action->execute(UserData::fromArray($request->validated()));

        return ApiResponse::created(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, UserModel $user, UpdateUserAction $action): JsonResponse
    {
        return ApiResponse::resource(new UserResource($action->execute($user, $request->validated())));
    }

    public function disable(Request $request, UserModel $user, DisableUserAction $action): JsonResponse
    {
        $this->authorizePermission($request, Permissions::USERS_MANAGE);

        return ApiResponse::resource(new UserResource($action->execute($user, $request->user()?->getKey())));
    }

    public function resetPassword(ResetUserPasswordRequest $request, UserModel $user, ResetPasswordAction $action): JsonResponse
    {
        $action->execute($user, (string) $request->input('password'));

        return ApiResponse::message('تم إعادة تعيين كلمة المرور.');
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission) === true, 403, 'ليست لديك صلاحية لهذا الإجراء.');
    }
}
