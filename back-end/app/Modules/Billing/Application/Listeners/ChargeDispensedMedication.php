<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Pharmacy\Domain\Events\MedicationDispensed;

/**
 * Medicine handed over is money owed. Runs inside the dispensing transaction,
 * so a failure here rolls the dispense back rather than leaving stock gone
 * and unbilled.
 */
final class ChargeDispensedMedication
{
    public function __construct(private readonly CreateChargeAction $createCharge) {}

    public function handle(MedicationDispensed $event): void
    {
        if (! config('hms.billing.auto_charge', true)) {
            return;
        }

        $dispense = $event->dispense;

        if ($dispense->encounter_id === null || (float) $dispense->total_price <= 0) {
            return;
        }

        $dispense->loadMissing('medication');

        $this->createCharge->execute([
            'encounter_id' => $dispense->encounter_id,
            'description' => $dispense->medication?->displayName() ?? 'دواء',
            'quantity' => $dispense->quantity,
            'unit_price' => $dispense->unit_price,
            'source_type' => ChargeSource::MedicationDispense->value,
            'source_id' => $dispense->getKey(),
        ]);
    }
}
