<?php

declare(strict_types=1);

namespace App\Modules\Assets\Providers;

use App\Core\Support\Providers\ModuleServiceProvider;

final class AssetsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Assets';
    }
}
