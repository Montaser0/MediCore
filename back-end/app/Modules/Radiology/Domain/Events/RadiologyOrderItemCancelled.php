<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Domain\Events;

use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;
use Illuminate\Foundation\Events\Dispatchable;

final class RadiologyOrderItemCancelled
{
    use Dispatchable;

    public function __construct(public readonly RadiologyOrderItemModel $item) {}
}
