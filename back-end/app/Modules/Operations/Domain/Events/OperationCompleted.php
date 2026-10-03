<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\Events;

use App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Billing listens for this to charge the operation and the materials used.
 */
final class OperationCompleted
{
    use Dispatchable;

    public function __construct(public readonly OperationModel $operation) {}
}
