<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Providers;

use App\Core\Support\OrderFulfillerRegistry;
use App\Core\Support\PatientRecordRegistry;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Pharmacy\Application\Actions\FulfilPharmacyOrderAction;
use App\Modules\Pharmacy\Infrastructure\PatientRecord\PharmacyRecordContributor;

final class PharmacyServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Pharmacy';
    }

    protected function bootModule(): void
    {
        $this->app->make(OrderFulfillerRegistry::class)
            ->register($this->app->make(FulfilPharmacyOrderAction::class));

        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(PharmacyRecordContributor::class));
    }
}
