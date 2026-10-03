<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Appointments\Presentation\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:'.Permissions::APPOINTMENTS_VIEW])->group(function (): void {
    Route::get('appointments', [AppointmentController::class, 'index']);
    Route::get('appointments/today', [AppointmentController::class, 'today']);
    Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::get('appointments/{appointment}/status-history', [AppointmentController::class, 'statusHistory']);
    Route::get('patients/{patient}/appointments', [AppointmentController::class, 'forPatient']);
});

// The write side validates its own permission inside each form request.
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('appointments', [AppointmentController::class, 'store']);
    Route::patch('appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);

    Route::middleware('permission:'.Permissions::APPOINTMENTS_UPDATE)->group(function (): void {
        Route::post('appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
        Route::post('appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn']);
        Route::post('appointments/{appointment}/no-show', [AppointmentController::class, 'markNoShow']);
    });
});
