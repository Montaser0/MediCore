<?php

declare(strict_types=1);

namespace App\Modules\Patients\Providers;

use App\Core\Support\Providers\ModuleServiceProvider;

final class PatientsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Patients';
    }
}
