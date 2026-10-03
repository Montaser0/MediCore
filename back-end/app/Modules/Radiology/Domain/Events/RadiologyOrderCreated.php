<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Domain\Events;

use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderModel;
use Illuminate\Foundation\Events\Dispatchable;

final class RadiologyOrderCreated
{
    use Dispatchable;

    public function __construct(public readonly RadiologyOrderModel $radiologyOrder) {}
}
