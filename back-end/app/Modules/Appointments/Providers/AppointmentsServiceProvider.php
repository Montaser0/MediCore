<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Providers;

use App\Core\Support\Providers\ModuleServiceProvider;

final class AppointmentsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Appointments';
    }
}
