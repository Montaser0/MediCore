<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Providers;

use App\Core\Support\Contracts\NotificationDispatcher;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Notifications\Application\Actions\DispatchNotificationAction;

final class NotificationsServiceProvider extends ModuleServiceProvider
{
    public array $singletons = [
        NotificationDispatcher::class => DispatchNotificationAction::class,
    ];

    protected function moduleName(): string
    {
        return 'Notifications';
    }
}
