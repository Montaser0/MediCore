<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Foundation\Events\Dispatchable;

final class UserCreated
{
    use Dispatchable;

    public function __construct(public readonly UserModel $user) {}
}
