<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Providers;

use App\Core\Support\Contracts\StockService;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Inventory\Infrastructure\Services\StockServiceAdapter;

final class InventoryServiceProvider extends ModuleServiceProvider
{
    public array $singletons = [
        StockService::class => StockServiceAdapter::class,
    ];

    protected function moduleName(): string
    {
        return 'Inventory';
    }
}
