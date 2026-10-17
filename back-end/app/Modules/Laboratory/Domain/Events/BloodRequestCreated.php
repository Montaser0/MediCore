<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Events;

use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodRequestModel;
use Illuminate\Foundation\Events\Dispatchable;

final class BloodRequestCreated
{
    use Dispatchable;

    public function __construct(public readonly BloodRequestModel $request) {}
}
