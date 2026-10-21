<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Assets\Presentation\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('assets')->group(function (): void {
    Route::middleware('permission:'.Permissions::ASSETS_VIEW)->group(function (): void {
        Route::get('summary', [AssetController::class, 'summary']);
        Route::get('categories', [AssetController::class, 'categories']);
        Route::get('statuses', [AssetController::class, 'statuses']);
        Route::get('/', [AssetController::class, 'index']);
        Route::get('{asset}/movements', [AssetController::class, 'movements']);
        Route::get('{asset}', [AssetController::class, 'show']);
    });

    Route::post('/', [AssetController::class, 'store']);
    Route::patch('{asset}', [AssetController::class, 'update']);
    Route::delete('{asset}', [AssetController::class, 'destroy']);
    Route::post('{asset}/assign', [AssetController::class, 'assign']);
    Route::post('{asset}/return', [AssetController::class, 'returnToStock']);
    Route::post('{asset}/maintenance', [AssetController::class, 'maintenance']);
    Route::post('{asset}/repair', [AssetController::class, 'repair']);
    Route::post('{asset}/retire', [AssetController::class, 'retire']);
});
