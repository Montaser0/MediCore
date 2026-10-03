<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Listeners;

use App\Modules\Identity\Domain\Events\UserLoggedIn;
use Illuminate\Support\Facades\Log;

final class TouchLastLogin
{
    public function handle(UserLoggedIn $event): void
    {
        Log::channel(config('logging.default'))->info('User signed in', [
            'user_id' => $event->user->getKey(),
            'username' => $event->user->username,
            'ip' => $event->ipAddress,
        ]);
    }
}
