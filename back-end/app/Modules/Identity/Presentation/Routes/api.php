<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Identity\Presentation\Controllers\AuthController;
use App\Modules\Identity\Presentation\Controllers\RoleController;
use App\Modules\Identity\Presentation\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('users', UserController::class)->only(['index', 'show', 'store', 'update']);
    Route::post('users/{user}/disable', [UserController::class, 'disable']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);

    Route::get('permissions', [RoleController::class, 'permissions'])
        ->middleware('permission:'.Permissions::ROLES_MANAGE);

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('permission:'.Permissions::ROLES_MANAGE);

    Route::post('roles', [RoleController::class, 'store']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
});
