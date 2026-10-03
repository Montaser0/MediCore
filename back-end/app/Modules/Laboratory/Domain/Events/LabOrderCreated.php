<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Events;

use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderModel;
use Illuminate\Foundation\Events\Dispatchable;

final class LabOrderCreated
{
    use Dispatchable;

    public function __construct(public readonly LabOrderModel $labOrder) {}
}
