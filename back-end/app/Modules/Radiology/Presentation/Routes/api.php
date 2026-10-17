<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Radiology\Presentation\Controllers\RadiologyReportController;
use App\Modules\Radiology\Presentation\Controllers\RadiologyServiceController;
use App\Modules\Radiology\Presentation\Controllers\RadiologyStudyController;
use App\Modules\Radiology\Presentation\Controllers\RadiologyWorklistController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('radiology')->group(function (): void {
    // Catalogue: readable by anyone who can raise an order.
    Route::middleware('permission:'.Permissions::ORDERS_VIEW)->group(function (): void {
        Route::get('services', [RadiologyServiceController::class, 'index']);
        Route::get('services/modalities', [RadiologyServiceController::class, 'modalities']);
        Route::get('injection-price', [RadiologyServiceController::class, 'injectionPrice']);
    });

    Route::put('injection-price', [RadiologyServiceController::class, 'updateInjectionPrice']);
    Route::post('services', [RadiologyServiceController::class, 'store']);
    Route::patch('services/{radiology_service}', [RadiologyServiceController::class, 'update']);
    Route::delete('services/{radiology_service}', [RadiologyServiceController::class, 'destroy']);

    Route::middleware('permission:'.Permissions::RADIOLOGY_ORDERS_VIEW)->group(function (): void {
        Route::get('worklist', [RadiologyWorklistController::class, 'index']);
        Route::get('items/{radiologyItem}', [RadiologyWorklistController::class, 'show']);
        Route::get('studies/{study}', [RadiologyStudyController::class, 'show']);
        Route::get('studies/{study}/images', [RadiologyStudyController::class, 'images']);
        Route::get('reports/{radiologyReport}', [RadiologyReportController::class, 'show']);
    });

    Route::post('items/{radiologyItem}/cancel', [RadiologyWorklistController::class, 'cancel']);
    Route::post('items/{radiologyItem}/studies', [RadiologyStudyController::class, 'store']);
    Route::post('studies/{study}/images', [RadiologyStudyController::class, 'uploadImages']);
    Route::post('studies/{study}/report', [RadiologyReportController::class, 'store']);
});
