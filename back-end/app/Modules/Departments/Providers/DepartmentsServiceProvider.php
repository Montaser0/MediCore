<?php

declare(strict_types=1);

namespace App\Modules\Departments\Providers;

use App\Core\Support\Providers\ModuleServiceProvider;

final class DepartmentsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Departments';
    }
}
