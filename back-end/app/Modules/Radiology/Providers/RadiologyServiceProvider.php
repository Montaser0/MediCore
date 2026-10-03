<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Providers;

use App\Core\Support\OrderFulfillerRegistry;
use App\Core\Support\PatientRecordRegistry;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Radiology\Application\Actions\CreateRadiologyOrderAction;
use App\Modules\Radiology\Infrastructure\PatientRecord\RadiologyRecordContributor;
use App\Modules\Radiology\Infrastructure\Services\DicomService;

final class RadiologyServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Radiology';
    }

    protected function registerModule(): void
    {
        $this->app->singleton(DicomService::class);
    }

    protected function bootModule(): void
    {
        $this->app->make(OrderFulfillerRegistry::class)
            ->register($this->app->make(CreateRadiologyOrderAction::class));

        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(RadiologyRecordContributor::class));
    }
}
