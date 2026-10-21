<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Inventory\Presentation\Controllers\InventoryItemController;
use App\Modules\Inventory\Presentation\Controllers\StockController;
use App\Modules\Inventory\Presentation\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('inventory')->group(function (): void {
    Route::get('warehouses', [WarehouseController::class, 'index'])
        ->middleware('permission:'.Permissions::INVENTORY_STOCK_VIEW.'|'.Permissions::PHARMACY_PRESCRIPTIONS_VIEW.'|'.Permissions::PHARMACY_DISPENSING_CREATE);

    Route::get('items/next-barcode', [InventoryItemController::class, 'nextBarcode'])
        ->middleware('permission:'.Permissions::INVENTORY_ITEMS_MANAGE.'|'.Permissions::PHARMACY_MEDICATIONS_MANAGE);

    Route::middleware('permission:'.Permissions::INVENTORY_STOCK_VIEW)->group(function (): void {
        Route::get('items', [InventoryItemController::class, 'index']);
        Route::get('items/types', [InventoryItemController::class, 'types']);
        Route::get('items/by-barcode/{barcode}', [InventoryItemController::class, 'byBarcode']);
        Route::get('items/{item}', [InventoryItemController::class, 'show']);
        Route::get('items/{item}/batches', [InventoryItemController::class, 'batches']);
        Route::get('stock-levels', [StockController::class, 'levels']);
        Route::get('movements', [StockController::class, 'movements']);
        Route::get('alerts', [StockController::class, 'alerts']);
    });

    // Each write route's permission is enforced by its form request.
    Route::post('warehouses', [WarehouseController::class, 'store']);
    Route::patch('warehouses/{warehouse}', [WarehouseController::class, 'update']);
    Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy']);

    Route::post('items/generate-barcodes', [InventoryItemController::class, 'generateBarcodes']);
    Route::post('items', [InventoryItemController::class, 'store']);
    Route::patch('items/{item}', [InventoryItemController::class, 'update']);
    Route::delete('items/{item}', [InventoryItemController::class, 'destroy']);

    Route::post('stock/receive', [StockController::class, 'receive']);
    Route::post('stock/issue', [StockController::class, 'issue']);
    Route::post('stock/transfer', [StockController::class, 'transfer']);
    Route::post('stock/adjust', [StockController::class, 'adjust']);
    Route::post('stock/write-off-expired', [StockController::class, 'writeOffExpired'])
        ->middleware('permission:'.Permissions::INVENTORY_STOCK_ADJUST);
});
