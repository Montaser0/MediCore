<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Inpatient\Domain\Events\PatientDischarged;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedAssignmentModel;

/**
 * Bed charges are raised per bed occupied, not per stay, because a patient
 * transferred from a general ward to intensive care pays two different rates.
 */
final class ChargeInpatientStay
{
    public function __construct(private readonly CreateChargeAction $createCharge) {}

    public function handle(PatientDischarged $event): void
    {
        if (! config('hms.billing.auto_charge', true)) {
            return;
        }

        $admission = $event->admission;
        $admission->loadMissing('bedAssignments.bed.room.ward');

        foreach ($admission->bedAssignments as $assignment) {
            $this->chargeAssignment($assignment, (int) $admission->encounter_id);
        }
    }

    private function chargeAssignment(BedAssignmentModel $assignment, int $encounterId): void
    {
        $rate = $assignment->bed?->room?->effectiveDailyRate() ?? 0.0;

        if ($rate <= 0) {
            return;
        }

        $this->createCharge->execute([
            'encounter_id' => $encounterId,
            'description' => 'أجرة السرير — '.($assignment->bed?->fullLabel() ?? 'الجناح'),
            'quantity' => $assignment->nights(),
            'unit_price' => $rate,
            'source_type' => ChargeSource::BedAssignment->value,
            'source_id' => $assignment->getKey(),
        ]);
    }
}
