<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Identity\Domain\Events\UserLoggedIn;
use App\Modules\Identity\Infrastructure\Listeners\TouchLastLogin;

final class IdentityServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        UserLoggedIn::class => [TouchLastLogin::class],
    ];

    protected function moduleName(): string
    {
        return 'Identity';
    }
}
