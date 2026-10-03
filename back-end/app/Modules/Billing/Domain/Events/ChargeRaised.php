<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Events;

use App\Modules\Billing\Infrastructure\Persistence\Models\ChargeModel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new charge line exists. If the visit is already closed, billing issues
 * it immediately instead of leaving it uninvoiced.
 */
final class ChargeRaised
{
    use Dispatchable;

    public function __construct(public readonly ChargeModel $charge) {}
}
