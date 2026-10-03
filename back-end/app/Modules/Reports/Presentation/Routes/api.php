<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Reports\Presentation\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:'.Permissions::REPORTS_VIEW])
    ->prefix('reports')
    ->group(function (): void {
        Route::get('dashboard', [ReportController::class, 'dashboard']);
        Route::get('financial', [ReportController::class, 'financial']);
        Route::get('activity', [ReportController::class, 'activity']);
        Route::get('inventory', [ReportController::class, 'inventory']);
        Route::get('export/payments', [ReportController::class, 'exportPayments']);
    });
