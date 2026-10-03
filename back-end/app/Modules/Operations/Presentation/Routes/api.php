<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Operations\Presentation\Controllers\OperationController;
use App\Modules\Operations\Presentation\Controllers\OperationTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('operations')->group(function (): void {
    Route::middleware('permission:'.Permissions::OPERATIONS_VIEW)->group(function (): void {
        Route::get('types', [OperationTypeController::class, 'index']);
        Route::get('theatres', [OperationTypeController::class, 'theatres']);
        Route::get('/', [OperationController::class, 'index']);
        Route::get('{operation}', [OperationController::class, 'show']);
    });

    Route::post('types', [OperationTypeController::class, 'store']);
    Route::patch('types/{type}', [OperationTypeController::class, 'update']);
    Route::delete('types/{type}', [OperationTypeController::class, 'destroy']);
    Route::post('theatres', [OperationTypeController::class, 'storeTheatre'])
        ->middleware('permission:'.Permissions::SETTINGS_MANAGE);

    Route::post('/', [OperationController::class, 'store']);

    Route::middleware('permission:'.Permissions::OPERATIONS_UPDATE)->group(function (): void {
        Route::post('{operation}/consent', [OperationController::class, 'recordConsent']);
        Route::post('{operation}/status', [OperationController::class, 'changeStatus']);
        Route::post('{operation}/notes', [OperationController::class, 'addNote']);
        Route::post('{operation}/materials', [OperationController::class, 'recordMaterials']);
    });

    Route::post('{operation}/complete', [OperationController::class, 'complete']);
});
