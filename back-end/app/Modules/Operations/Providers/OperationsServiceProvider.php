<?php

declare(strict_types=1);

namespace App\Modules\Operations\Providers;

use App\Core\Support\PatientRecordRegistry;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Operations\Infrastructure\PatientRecord\OperationsRecordContributor;

final class OperationsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Operations';
    }

    protected function bootModule(): void
    {
        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(OperationsRecordContributor::class));
    }
}
