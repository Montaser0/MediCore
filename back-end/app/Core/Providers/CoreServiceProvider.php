<?php

declare(strict_types=1);

namespace App\Core\Providers;

use App\Core\Auth\CurrentUser;
use App\Core\Authorization\PermissionChecker;
use App\Core\Http\Controllers\SystemModulesController;
use App\Core\Modules\ModuleRegistry;
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
    }
}
