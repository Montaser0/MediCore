<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Clinical\Presentation\Controllers\ClinicalController;
use App\Modules\Clinical\Presentation\Controllers\DiagnosisCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('diagnosis-codes', [DiagnosisCodeController::class, 'index'])
        ->middleware('permission:'.Permissions::CLINICAL_DIAGNOSIS_VIEW);
    Route::post('diagnosis-codes', [DiagnosisCodeController::class, 'store']);
    Route::patch('diagnosis-codes/{diagnosisCode}', [DiagnosisCodeController::class, 'update']);
    Route::delete('diagnosis-codes/{diagnosisCode}', [DiagnosisCodeController::class, 'destroy']);

    Route::prefix('encounters/{encounter}')->group(function (): void {
        Route::get('vitals', [ClinicalController::class, 'vitals'])
            ->middleware('permission:'.Permissions::CLINICAL_VITALS_VIEW);
        Route::post('vitals', [ClinicalController::class, 'storeVitals']);

        Route::get('notes', [ClinicalController::class, 'notes'])
            ->middleware('permission:'.Permissions::CLINICAL_NOTES_VIEW);
        Route::post('notes', [ClinicalController::class, 'storeNote']);

        Route::get('diagnoses', [ClinicalController::class, 'diagnoses'])
            ->middleware('permission:'.Permissions::CLINICAL_DIAGNOSIS_VIEW);
        Route::post('diagnoses', [ClinicalController::class, 'storeDiagnosis']);

        Route::get('procedures', [ClinicalController::class, 'procedures'])
            ->middleware('permission:'.Permissions::CLINICAL_NOTES_VIEW);
        Route::post('procedures', [ClinicalController::class, 'storeProcedure']);
    });
});
