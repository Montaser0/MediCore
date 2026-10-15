<?php

declare(strict_types=1);

use App\Modules\Hospital\Presentation\Controllers\DoctorController;
use App\Modules\Hospital\Presentation\Controllers\HospitalController;
use App\Modules\Hospital\Presentation\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('hospitals', [HospitalController::class, 'index']);
    Route::get('hospitals/current', [HospitalController::class, 'current']);
    Route::put('hospitals/{hospital}', [HospitalController::class, 'update']);

    Route::get('staff/access', [StaffController::class, 'access']);

    Route::apiResource('staff', StaffController::class)
        ->parameters(['staff' => 'staff'])
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    Route::apiResource('doctors', DoctorController::class)->only(['index', 'show']);
});
