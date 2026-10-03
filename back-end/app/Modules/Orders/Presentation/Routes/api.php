<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Orders\Presentation\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:'.Permissions::ORDERS_VIEW])->group(function (): void {
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::get('encounters/{encounter}/orders', [OrderController::class, 'forEncounter']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('orders', [OrderController::class, 'store']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('orders/{order}/accept', [OrderController::class, 'accept'])
        ->middleware('permission:'.Permissions::ORDERS_VIEW);
});
