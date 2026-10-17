<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Events;

use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use Illuminate\Foundation\Events\Dispatchable;

final class LabOrderItemCancelled
{
    use Dispatchable;

    public function __construct(public readonly LabOrderItemModel $item) {}
}
