<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Patients\Presentation\Controllers\PatientController;
use App\Modules\Patients\Presentation\Controllers\PatientProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:'.Permissions::PATIENTS_VIEW])->group(function (): void {
    Route::get('patients', [PatientController::class, 'index']);
    Route::get('patients/{patient}', [PatientController::class, 'show']);
    Route::get('patients/{patient}/header', [PatientController::class, 'header']);
    Route::get('patients/{patient}/file', [PatientController::class, 'file']);

    Route::get('patients/{patient}/allergies', [PatientProfileController::class, 'allergies']);
    Route::get('patients/{patient}/conditions', [PatientProfileController::class, 'conditions']);
    Route::get('patients/{patient}/contacts', [PatientProfileController::class, 'contacts']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('patients', [PatientController::class, 'store']);
    Route::put('patients/{patient}', [PatientController::class, 'update']);
    Route::delete('patients/{patient}', [PatientController::class, 'destroy']);

    Route::post('patients/{patient}/allergies', [PatientProfileController::class, 'storeAllergy']);
    Route::put('patient-allergies/{allergy}', [PatientProfileController::class, 'updateAllergy']);
    Route::delete('patient-allergies/{allergy}', [PatientProfileController::class, 'destroyAllergy']);

    Route::post('patients/{patient}/conditions', [PatientProfileController::class, 'storeCondition']);
    Route::put('patient-conditions/{condition}', [PatientProfileController::class, 'updateCondition']);
    Route::delete('patient-conditions/{condition}', [PatientProfileController::class, 'destroyCondition']);

    Route::post('patients/{patient}/contacts', [PatientProfileController::class, 'storeContact']);
    Route::delete('patient-contacts/{contact}', [PatientProfileController::class, 'destroyContact']);
});
