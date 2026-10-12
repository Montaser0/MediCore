<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->resolveModuleFactories();
        $this->logSlowQueries();
    }

    /**
     * Enabled only when DB_SLOW_QUERY_MS is set. The log names the SQL;
     * the plan itself is checked with `php artisan lookup:explain`.
     */
    private function logSlowQueries(): void
    {
        $threshold = (int) config('database.slow_query_ms');

        if ($threshold < 1 || $this->app->environment('testing')) {
            return;
        }

        DB::listen(static function ($query) use ($threshold): void {
            if ($query->time < $threshold) {
                return;
            }

            Log::warning('slow query', [
                'ms' => $query->time,
                'sql' => $query->sql,
            ]);
        });
    }

    /**
     * Models live inside their module rather than in app/Models, so Laravel's
     * default guess (Database\Factories\FooFactory) never finds them. Each
     * module keeps its factories beside its models instead.
     */
    private function resolveModuleFactories(): void
    {
        Factory::guessFactoryNamesUsing(static function (string $modelName): string {
            $factoryName = str_replace(
                '\\Persistence\\Models\\',
                '\\Persistence\\Factories\\',
                $modelName,
            );

            // FooModel -> FooFactory, so the pair reads naturally together.
            return preg_replace('/Model$/', '', $factoryName).'Factory';
        });
    }
}
