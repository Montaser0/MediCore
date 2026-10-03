<?php

declare(strict_types=1);

use App\Modules\Settings\Presentation\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('settings')->group(function (): void {
    Route::get('/', [SettingController::class, 'index']);
    Route::put('/', [SettingController::class, 'update']);
    Route::delete('/', [SettingController::class, 'destroy']);
});
