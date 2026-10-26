<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Core\Modules\ModuleRegistry;
use App\Modules\Pharmacy\Presentation\Controllers\DispenseController;
use App\Modules\Pharmacy\Presentation\Controllers\DrugCatalogueController;
use App\Modules\Pharmacy\Presentation\Controllers\MedicationController;
use App\Modules\Pharmacy\Presentation\Controllers\MedicationStockController;
use App\Modules\Pharmacy\Presentation\Controllers\PharmacyTillController;
use App\Modules\Pharmacy\Presentation\Controllers\PharmacyWarehouseController;
use App\Modules\Pharmacy\Presentation\Controllers\PosController;
use App\Modules\Pharmacy\Presentation\Controllers\PrescriptionController;
use Illuminate\Support\Facades\Route;

$registry = app(ModuleRegistry::class);

$catalogueRead = 'permission:'.Permissions::PHARMACY_MEDICATIONS_MANAGE.'|'.Permissions::PHARMACY_PRESCRIPTIONS_CREATE.'|'.Permissions::ORDERS_VIEW.'|'.Permissions::ENCOUNTERS_CREATE;

Route::middleware(['auth:sanctum', $catalogueRead])->prefix('pharmacy')->group(function (): void {
    Route::get('catalogue', [DrugCatalogueController::class, 'index']);
});

if ($registry->feature('pharmacy.pos')) {
    $till = 'permission:'.Permissions::PHARMACY_MEDICATIONS_MANAGE.'|'.Permissions::PHARMACY_DISPENSING_CREATE;

    Route::middleware(['auth:sanctum', 'permission:'.Permissions::PHARMACY_MEDICATIONS_MANAGE])
        ->post('pharmacy/catalogue/{catalogueItem}/stock', [DrugCatalogueController::class, 'stock']);

    Route::middleware(['auth:sanctum', $till])->prefix('pharmacy/pos')->group(function (): void {
        Route::get('medications', [PosController::class, 'index']);
        Route::post('medications', [PosController::class, 'store']);
        Route::patch('medications/{medication}', [PosController::class, 'update']);
        Route::get('barcode/{barcode}', [PosController::class, 'barcode']);
        Route::post('sales', [PosController::class, 'sell']);
        Route::get('sales', [PosController::class, 'sales']);
        Route::get('sales/{saleNumber}', [PosController::class, 'sale']);
        Route::get('customers', [PharmacyTillController::class, 'customers']);
        Route::get('debts', [PharmacyTillController::class, 'debts']);
        Route::post('debts/payments', [PharmacyTillController::class, 'collect']);
        Route::get('purchases', [PharmacyTillController::class, 'purchases']);
        Route::post('purchases', [PharmacyTillController::class, 'storePurchase']);
        Route::post('purchases/payments', [PharmacyTillController::class, 'payPurchase']);
        Route::post('returns', [PharmacyTillController::class, 'storeReturn']);
        Route::get('stocktakes/open', [PharmacyTillController::class, 'currentStocktake']);
        Route::post('stocktakes', [PharmacyTillController::class, 'openStocktake']);
        Route::patch('stocktakes/{stocktake}/lines/{line}', [PharmacyTillController::class, 'countLine']);
        Route::post('stocktakes/{stocktake}/post', [PharmacyTillController::class, 'postStocktake']);
    });
}

Route::middleware(['auth:sanctum', 'permission:'.Permissions::PHARMACY_MEDICATIONS_MANAGE])
    ->prefix('pharmacy')
    ->group(function (): void {
        Route::get('warehouses', [PharmacyWarehouseController::class, 'index']);
        Route::post('warehouses', [PharmacyWarehouseController::class, 'store']);
        Route::post('catalogue/{catalogueItem}/adopt', [DrugCatalogueController::class, 'adopt']);
        Route::post('medications/{medication}/provision', [MedicationStockController::class, 'provision']);
        Route::post('medications/{medication}/receive', [MedicationStockController::class, 'receive']);
    });

if (! $registry->feature('pharmacy.prescriptions')) {
    return;
}

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
