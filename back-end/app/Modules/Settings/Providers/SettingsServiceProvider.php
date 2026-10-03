<?php

declare(strict_types=1);

namespace App\Modules\Settings\Providers;

use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Settings\Application\Services\SettingsRepository;

final class SettingsServiceProvider extends ModuleServiceProvider
{
    public array $singletons = [
        SettingsRepository::class => SettingsRepository::class,
    ];

    protected function moduleName(): string
    {
        return 'Settings';
    }
}
