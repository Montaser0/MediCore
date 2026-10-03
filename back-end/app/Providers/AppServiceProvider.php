<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
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
