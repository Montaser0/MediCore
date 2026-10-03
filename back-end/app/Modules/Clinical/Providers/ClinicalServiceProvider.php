<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Providers;

use App\Core\Support\PatientRecordRegistry;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Clinical\Infrastructure\PatientRecord\ClinicalRecordContributor;

final class ClinicalServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Clinical';
    }

    protected function bootModule(): void
    {
        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(ClinicalRecordContributor::class));
    }
}
