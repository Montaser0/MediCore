<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Providers;

use App\Core\Support\Providers\ModuleServiceProvider;

final class HospitalServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Hospital';
    }
}
