<?php

declare(strict_types=1);

namespace App\Core\Providers;

use App\Core\Auth\CurrentUser;
use App\Core\Excel\ExcelController;
use App\Core\Authorization\PermissionChecker;
use App\Core\Backup\BackupController;
use App\Core\Backup\CreateBackupCommand;
use App\Core\Backup\RestoreBackupCommand;
use App\Core\Http\Controllers\SystemModulesController;
use App\Core\Modules\ModuleRegistry;
use App\Core\Setup\PharmacySetupController;
use App\Core\Storage\FileStorage;
use App\Core\Storage\StorageManager;
use App\Core\Support\Numbering\NumberGenerator;
use App\Core\Support\OrderFulfillerRegistry;
use App\Core\Support\PatientRecordRegistry;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry((string) config('modules.edition')));
        $this->app->singleton(CurrentUser::class);
        $this->app->singleton(PermissionChecker::class);
        $this->app->singleton(NumberGenerator::class);
        $this->app->singleton(PatientRecordRegistry::class);
        $this->app->singleton(OrderFulfillerRegistry::class);
        $this->app->singleton(FileStorage::class, StorageManager::class);

        $this->commands([
            CreateBackupCommand::class,
            RestoreBackupCommand::class,
        ]);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->get('system/modules', [SystemModulesController::class, 'show']);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('username')).'|'.$request->ip()));

        RateLimiter::for('setup', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        Route::middleware(['api', 'auth:sanctum'])->prefix('api/excel')->group(function (): void {
            Route::get('{book}/template', [ExcelController::class, 'template']);
            Route::get('{book}/export', [ExcelController::class, 'export']);
            Route::post('{book}/import', [ExcelController::class, 'import']);
        });

        Route::middleware('api')->prefix('api')->group(function (): void {
            Route::get('setup/status', [PharmacySetupController::class, 'status']);
            Route::post('setup', [PharmacySetupController::class, 'store'])->middleware('throttle:setup');

            Route::middleware(['auth:sanctum', 'role:admin'])->group(function (): void {
                Route::get('system/backups', [BackupController::class, 'index']);
                Route::get('system/backup-drives', [BackupController::class, 'drives']);
                Route::post('system/backups', [BackupController::class, 'store']);
                Route::post('system/backups/restore', [BackupController::class, 'restore']);
            });
        });
    }
}
