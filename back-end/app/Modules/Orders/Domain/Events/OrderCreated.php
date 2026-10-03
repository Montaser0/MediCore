<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Events;

use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use Illuminate\Foundation\Events\Dispatchable;

final class OrderCreated
{
    use Dispatchable;

    public function __construct(public readonly OrderModel $order) {}
}
