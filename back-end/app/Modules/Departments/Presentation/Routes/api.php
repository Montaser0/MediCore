<?php

declare(strict_types=1);

use App\Modules\Departments\Presentation\Controllers\ClinicController;
use App\Modules\Departments\Presentation\Controllers\DepartmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('departments/types', [DepartmentController::class, 'types']);

    Route::apiResource('departments', DepartmentController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::apiResource('clinics', ClinicController::class)->only(['index', 'store', 'update', 'destroy']);
});
