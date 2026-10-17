<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Providers;

use App\Core\Support\OrderFulfillerRegistry;
use App\Core\Support\PatientRecordRegistry;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Laboratory\Application\Actions\CreateLabOrderAction;
use App\Modules\Laboratory\Infrastructure\PatientRecord\BloodRecordContributor;
use App\Modules\Laboratory\Infrastructure\PatientRecord\LaboratoryRecordContributor;

final class LaboratoryServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Laboratory';
    }

    protected function bootModule(): void
    {
        // Claims `laboratory` orders coming out of the order engine.
        $this->app->make(OrderFulfillerRegistry::class)
            ->register($this->app->make(CreateLabOrderAction::class));

        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(LaboratoryRecordContributor::class));

        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(BloodRecordContributor::class));
    }
}
