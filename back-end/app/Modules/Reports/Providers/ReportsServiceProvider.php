<?php

declare(strict_types=1);

namespace App\Modules\Reports\Providers;

use App\Core\Support\Providers\ModuleServiceProvider;

/**
 * Reports own no tables; they read from every other module through its
 * models, which is why this module has no migrations of its own.
 */
final class ReportsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Reports';
    }
}
