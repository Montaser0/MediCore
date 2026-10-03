<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Providers;

use App\Core\Support\PatientRecordRegistry;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Inpatient\Infrastructure\PatientRecord\InpatientRecordContributor;

final class InpatientServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Inpatient';
    }

    protected function bootModule(): void
    {
        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(InpatientRecordContributor::class));
    }
}
