<?php

declare(strict_types=1);

namespace App\Core\Support\Providers;

use App\Core\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base provider for every module. It wires up the module's own routes,
 * migrations and event listeners, which is what keeps a module self-contained
 * rather than spread across the global config.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Module directory name under app/Modules.
     */
    abstract protected function moduleName(): string;

    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected array $listen = [];

    /**
     * Laravel reads these two off the provider itself, so they must stay
     * public and keep these names.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [];

    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [];

    public function register(): void
    {
        $this->registerModule();
    }

    public function boot(): void
    {
        // The schema stays the same on every server. A closed module simply
        // does not publish routes, listeners or record sections.
        $this->loadMigrations();

        if (! $this->app->make(ModuleRegistry::class)->enabled($this->moduleName())) {
            return;
        }

        $this->loadRoutes();
        $this->registerListeners();
        $this->bootModule();
    }

    protected function registerModule(): void {}

    protected function bootModule(): void {}

    protected function modulePath(string $append = ''): string
    {
        $base = app_path('Modules/'.$this->moduleName());

        return $append === '' ? $base : $base.DIRECTORY_SEPARATOR.ltrim($append, '/\\');
    }

    private function loadRoutes(): void
    {
        $routes = $this->modulePath('Presentation/Routes/api.php');

        if (! is_file($routes)) {
            return;
        }

        Route::middleware('api')
            ->prefix('api')
            ->group($routes);
    }

    private function loadMigrations(): void
    {
        $migrations = $this->modulePath('Infrastructure/Persistence/Migrations');

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }
    }

    private function registerListeners(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
