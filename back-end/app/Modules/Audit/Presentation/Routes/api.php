<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use App\Modules\Audit\Presentation\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:'.Permissions::AUDIT_VIEW])
    ->prefix('audit')
    ->group(function (): void {
        Route::get('logs', [AuditLogController::class, 'index']);
        Route::get('logs/{entityType}/{entityId}', [AuditLogController::class, 'forEntity']);
    });
