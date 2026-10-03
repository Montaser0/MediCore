<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Pharmacy\Presentation\Controllers\DispenseController;
use App\Modules\Pharmacy\Presentation\Controllers\MedicationController;
use App\Modules\Pharmacy\Presentation\Controllers\PrescriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('pharmacy')->group(function (): void {
    // Formulary: the doctor prescribing from it needs to read it.
    Route::middleware('permission:'.Permissions::ORDERS_VIEW)->group(function (): void {
        Route::get('medications', [MedicationController::class, 'index']);
        Route::get('medications/categories', [MedicationController::class, 'categories']);
        Route::get('medications/{medication}', [MedicationController::class, 'show']);
    });

    Route::post('medications', [MedicationController::class, 'store']);
    Route::patch('medications/{medication}', [MedicationController::class, 'update']);
    Route::delete('medications/{medication}', [MedicationController::class, 'destroy']);

    Route::middleware('permission:'.Permissions::PHARMACY_PRESCRIPTIONS_VIEW)->group(function (): void {
        Route::get('prescriptions', [PrescriptionController::class, 'index']);
        Route::get('prescriptions/{prescription}', [PrescriptionController::class, 'show']);
        Route::get('dispenses', [DispenseController::class, 'index']);
        Route::get('prescription-items/{prescriptionItem}/availability', [DispenseController::class, 'availability']);
    });

    Route::post('prescriptions', [PrescriptionController::class, 'store']);
    Route::post('prescriptions/{prescription}/activate', [PrescriptionController::class, 'activate'])
        ->middleware('permission:'.Permissions::PHARMACY_PRESCRIPTIONS_CREATE);
    Route::post('prescriptions/{prescription}/cancel', [PrescriptionController::class, 'cancel'])
        ->middleware('permission:'.Permissions::PHARMACY_PRESCRIPTIONS_CREATE);

    Route::post('prescription-items/{prescriptionItem}/dispense', [DispenseController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'permission:'.Permissions::PHARMACY_PRESCRIPTIONS_VIEW])->group(function (): void {
    Route::get('encounters/{encounter}/prescriptions', [PrescriptionController::class, 'forEncounter']);
});
