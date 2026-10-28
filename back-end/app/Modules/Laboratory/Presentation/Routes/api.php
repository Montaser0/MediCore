<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Laboratory\Presentation\Controllers\BloodProductController;
use App\Modules\Laboratory\Presentation\Controllers\BloodRequestController;
use App\Modules\Laboratory\Presentation\Controllers\LabResultController;
use App\Modules\Laboratory\Presentation\Controllers\LabSampleController;
use App\Modules\Laboratory\Presentation\Controllers\LabTestController;
use App\Modules\Laboratory\Presentation\Controllers\LabWorklistController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('laboratory')->group(function (): void {
    // Catalogue: anyone who can raise an order needs to read it.
        Route::middleware('permission:'.Permissions::ORDERS_VIEW.'|'.Permissions::ENCOUNTERS_CREATE)->group(function (): void {
        Route::get('tests', [LabTestController::class, 'index']);
        Route::get('tests/categories', [LabTestController::class, 'categories']);
        Route::get('tests/{lab_test}', [LabTestController::class, 'show']);
        Route::get('blood-products', [BloodProductController::class, 'index']);
    });

    Route::post('tests', [LabTestController::class, 'store']);
    Route::patch('tests/{lab_test}', [LabTestController::class, 'update']);
    Route::delete('tests/{lab_test}', [LabTestController::class, 'destroy']);

    // Worklist and samples.
    Route::middleware('permission:'.Permissions::LABORATORY_ORDERS_VIEW)->group(function (): void {
        Route::get('orders/barcode/{barcode}', [LabWorklistController::class, 'showByBarcode'])
            ->where('barcode', '[0-9]{8,20}');
        Route::get('blood-requests/barcode/{barcode}', [BloodRequestController::class, 'showByBarcode'])
            ->where('barcode', '[0-9]{8,20}');
        Route::get('worklist', [LabWorklistController::class, 'index']);
        Route::get('blood-requests', [BloodRequestController::class, 'index']);
        Route::get('items/{labItem}', [LabWorklistController::class, 'show']);
        Route::get('results/{labResult}', [LabResultController::class, 'show']);
    });

    Route::post('items/{labItem}/samples', [LabSampleController::class, 'store']);
    Route::post('samples/{sample}/receive', [LabSampleController::class, 'receive'])
        ->middleware('permission:'.Permissions::LABORATORY_SAMPLES_MANAGE);
    Route::post('samples/{sample}/reject', [LabSampleController::class, 'reject']);

    Route::post('blood-products', [BloodProductController::class, 'store']);
    Route::patch('blood-products/{blood_product}', [BloodProductController::class, 'update']);
    Route::delete('blood-products/{blood_product}', [BloodProductController::class, 'destroy']);

    Route::post('blood-requests', [BloodRequestController::class, 'store']);
    Route::post('blood-requests/{bloodRequest}/result', [BloodRequestController::class, 'fulfill']);
    Route::post('blood-requests/{bloodRequest}/cancel', [BloodRequestController::class, 'cancel'])
        ->middleware('permission:'.Permissions::ORDERS_CANCEL);

    Route::post('items/{labItem}/result', [LabResultController::class, 'store']);
    Route::post('results/{labResult}/verify', [LabResultController::class, 'verify'])
        ->middleware('permission:'.Permissions::LABORATORY_RESULTS_VERIFY);

    Route::post('items/{labItem}/cancel', [LabWorklistController::class, 'cancel'])
        ->middleware('permission:'.Permissions::ORDERS_CANCEL);
});
