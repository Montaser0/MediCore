<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Nursing\Presentation\Controllers\MedicationAdministrationController;
use App\Modules\Nursing\Presentation\Controllers\NursingNoteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::middleware('permission:'.Permissions::NURSING_NOTES_VIEW)->group(function (): void {
        Route::get('encounters/{encounter}/nursing-notes', [NursingNoteController::class, 'index']);
        Route::get('encounters/{encounter}/care-plans', [NursingNoteController::class, 'carePlans']);
        Route::get('encounters/{encounter}/medication-administrations', [MedicationAdministrationController::class, 'index']);
        Route::get('nursing/worklist', [MedicationAdministrationController::class, 'worklist']);
    });

    Route::post('encounters/{encounter}/nursing-notes', [NursingNoteController::class, 'store']);
    Route::post('encounters/{encounter}/care-plans', [NursingNoteController::class, 'storeCarePlan']);
    Route::post('care-plans/{carePlan}/evaluate', [NursingNoteController::class, 'evaluateCarePlan'])
        ->middleware('permission:'.Permissions::NURSING_NOTES_CREATE);

    Route::post('prescription-items/{item}/schedule-administration', [MedicationAdministrationController::class, 'schedule'])
        ->middleware('permission:'.Permissions::NURSING_MEDICATION_ADMINISTER);
    Route::post('medication-administrations/{administration}/record', [MedicationAdministrationController::class, 'record']);
});
