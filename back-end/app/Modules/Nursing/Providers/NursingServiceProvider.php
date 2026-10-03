<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Providers;

use App\Core\Support\PatientRecordRegistry;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Nursing\Infrastructure\PatientRecord\NursingRecordContributor;

final class NursingServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Nursing';
    }

    protected function bootModule(): void
    {
        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(NursingRecordContributor::class));
    }
}
