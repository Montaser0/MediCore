<?php

declare(strict_types=1);

namespace App\Modules\Orders\Providers;

use App\Core\Support\Contracts\OrderCoordinator;
use App\Core\Support\PatientRecordRegistry;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Orders\Infrastructure\PatientRecord\OrdersRecordContributor;
use App\Modules\Orders\Infrastructure\Services\OrderCoordinatorService;

final class OrdersServiceProvider extends ModuleServiceProvider
{
    public array $singletons = [
        OrderCoordinator::class => OrderCoordinatorService::class,
    ];

    protected function moduleName(): string
    {
        return 'Orders';
    }

    protected function bootModule(): void
    {
        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(OrdersRecordContributor::class));
    }
}
