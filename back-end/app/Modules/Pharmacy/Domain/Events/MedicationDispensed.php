<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Events;

use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationDispenseModel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Billing listens for this to raise the charge, which keeps the pharmacy
 * free of any knowledge of prices and invoices.
 */
final class MedicationDispensed
{
    use Dispatchable;

    public function __construct(public readonly MedicationDispenseModel $dispense) {}
}
