<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Encounters\Presentation\Controllers\EncounterController;
use App\Modules\Encounters\Presentation\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:'.Permissions::ENCOUNTERS_VIEW])->group(function (): void {
    Route::get('encounters', [EncounterController::class, 'index']);
    Route::get('encounters/queue', [EncounterController::class, 'queue']);
    Route::get('encounters/{encounter}', [EncounterController::class, 'show']);
    Route::get('encounters/{encounter}/transfers', [EncounterController::class, 'transfers']);
    Route::get('encounters/{encounter}/status-history', [EncounterController::class, 'statusHistory']);
    Route::get('encounters/{encounter}/timeline', [TimelineController::class, 'forEncounter']);

    Route::get('patients/{patient}/encounters', [EncounterController::class, 'forPatient']);
    Route::get('patients/{patient}/timeline', [TimelineController::class, 'forPatient']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('encounters', [EncounterController::class, 'store']);
    Route::post('encounters/{encounter}/start', [EncounterController::class, 'start'])
        ->middleware('permission:'.Permissions::ENCOUNTERS_UPDATE);
    Route::post('encounters/{encounter}/transfer', [EncounterController::class, 'transfer']);
    Route::post('encounters/{encounter}/close', [EncounterController::class, 'close']);
});
