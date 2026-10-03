<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Domain\Events;

use App\Modules\Clinical\Infrastructure\Persistence\Models\ProcedureModel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A bedside or clinic procedure was recorded. Billing listens and raises
 * the charge from the linked price-list service.
 */
final class ProcedurePerformed
{
    use Dispatchable;

    public function __construct(public readonly ProcedureModel $procedure) {}
}
