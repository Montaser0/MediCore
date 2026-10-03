<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Billing\Presentation\Controllers\ChargeController;
use App\Modules\Billing\Presentation\Controllers\InvoiceController;
use App\Modules\Billing\Presentation\Controllers\PaymentController;
use App\Modules\Billing\Presentation\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('billing')->group(function (): void {
    // Price list: everyone raising a charge needs to read it.
    Route::get('services', [ServiceController::class, 'index'])
        ->middleware('permission:'.Permissions::BILLING_CHARGES_VIEW.'|'.Permissions::CLINICAL_PROCEDURES_CREATE);
    Route::post('services', [ServiceController::class, 'store']);
    Route::patch('services/{service}', [ServiceController::class, 'update']);
    Route::delete('services/{service}', [ServiceController::class, 'destroy']);

    Route::middleware('permission:'.Permissions::BILLING_CHARGES_VIEW)->group(function (): void {
        Route::get('charges', [ChargeController::class, 'index']);
    });

    Route::post('charges', [ChargeController::class, 'store']);

    Route::middleware('permission:'.Permissions::BILLING_INVOICES_VIEW)->group(function (): void {
        Route::get('invoices', [InvoiceController::class, 'index']);
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::get('payments', [PaymentController::class, 'index']);
    });

    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])
        ->middleware('permission:'.Permissions::BILLING_INVOICES_CREATE);
    Route::post('invoices/{invoice}/discount', [InvoiceController::class, 'applyDiscount']);
    Route::post('invoices/{invoice}/installments', [InvoiceController::class, 'installments'])
        ->middleware('permission:'.Permissions::BILLING_INVOICES_CREATE);
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
        ->middleware('permission:'.Permissions::BILLING_INVOICES_CREATE);

    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store']);
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])
        ->middleware('permission:'.Permissions::BILLING_DISCOUNTS_APPLY);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('patients/{patient}/account', [InvoiceController::class, 'patientAccount'])
        ->middleware('permission:'.Permissions::BILLING_INVOICES_VIEW);
    Route::get('encounters/{encounter}/charges', [ChargeController::class, 'forEncounter'])
        ->middleware('permission:'.Permissions::BILLING_CHARGES_VIEW);
});
