<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Identity\Application\Actions\AuthenticateUserAction;
use App\Modules\Identity\Application\Actions\LogoutUserAction;
use App\Modules\Identity\Application\Actions\ResetPasswordAction;
use App\Modules\Identity\Application\DTOs\CredentialsData;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Identity\Presentation\Requests\ChangePasswordRequest;
use App\Modules\Identity\Presentation\Requests\LoginRequest;
use App\Modules\Identity\Presentation\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends ApiController
{
    public function login(LoginRequest $request, AuthenticateUserAction $action): JsonResponse
    {
        $result = $action->execute(
            CredentialsData::fromArray($request->validated(), $request->ip())
        );

        $user = $result['user']->load(['staff.department', 'staff.doctor', 'roles', 'departments']);

        return ApiResponse::data([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new AuthenticatedUserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        return ApiResponse::resource(
            new AuthenticatedUserResource($user->load(['staff.department', 'staff.doctor', 'roles', 'departments']))
        );
    }

    public function logout(Request $request, LogoutUserAction $action): JsonResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        $action->execute($user, $request->boolean('all_devices'));

        return ApiResponse::message('تم تسجيل الخروج.');
    }

    public function changePassword(ChangePasswordRequest $request, ResetPasswordAction $action): JsonResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        $action->execute(
            user: $user,
            newPassword: (string) $request->input('password'),
            currentPassword: (string) $request->input('current_password'),
        );

        return ApiResponse::message('تم تحديث كلمة المرور. سجّل الدخول من جديد.');
    }
}
