<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Inpatient\Presentation\Controllers\AdmissionController;
use App\Modules\Inpatient\Presentation\Controllers\BedController;
use App\Modules\Inpatient\Presentation\Controllers\WardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('inpatient')->group(function (): void {
    Route::middleware('permission:'.Permissions::INPATIENT_VIEW)->group(function (): void {
        Route::get('wards', [WardController::class, 'index']);
        Route::get('beds', [BedController::class, 'index']);
        Route::get('beds/occupancy', [BedController::class, 'occupancy']);
        Route::get('admissions', [AdmissionController::class, 'index']);
        Route::get('admissions/{admission}', [AdmissionController::class, 'show']);
    });

    Route::post('wards', [WardController::class, 'store']);
    Route::patch('wards/{ward}', [WardController::class, 'update']);
    Route::delete('wards/{ward}', [WardController::class, 'destroy']);
    Route::post('wards/{ward}/rooms', [WardController::class, 'storeRoom'])
        ->middleware('permission:'.Permissions::INPATIENT_BEDS_MANAGE);
    Route::patch('rooms/{room}', [WardController::class, 'updateRoom'])
        ->middleware('permission:'.Permissions::INPATIENT_BEDS_MANAGE);
    Route::delete('rooms/{room}', [WardController::class, 'destroyRoom'])
        ->middleware('permission:'.Permissions::INPATIENT_BEDS_MANAGE);
    Route::post('beds', [BedController::class, 'store']);
    Route::post('beds/{bed}/status', [BedController::class, 'changeStatus'])
        ->middleware('permission:'.Permissions::INPATIENT_BEDS_MANAGE);

    Route::post('admissions', [AdmissionController::class, 'store']);
    Route::post('admissions/{admission}/transfer', [AdmissionController::class, 'transfer']);
    Route::post('admissions/{admission}/discharge', [AdmissionController::class, 'discharge']);
});
