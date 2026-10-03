<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Billing\Infrastructure\Persistence\Models\ServiceModel;
use App\Modules\Encounters\Domain\Enums\EncounterType;
use App\Modules\Encounters\Domain\Events\EncounterStarted;

/**
 * Opening the examination bills the visit itself. The fee comes from the
 * price list and follows the visit type, so reception does not type it.
 */
final class ChargeStartedEncounter
{
    public function __construct(private readonly CreateChargeAction $createCharge) {}

    public function handle(EncounterStarted $event): void
    {
        if (! config('hms.billing.auto_charge', true)) {
            return;
        }

        $encounter = $event->encounter;

        if ($encounter->type === EncounterType::Operation) {
            return;
        }

        $code = match ($encounter->type) {
            EncounterType::Emergency => 'CONS-ER',
            EncounterType::FollowUp => 'CONS-FU',
            EncounterType::Inpatient => 'CONS-SPEC',
            default => 'CONS-GEN',
        };

        $service = ServiceModel::query()->active()->where('code', $code)->first();
        $price = (float) ($service?->price ?? 0);

        if ($service === null || $price <= 0) {
            return;
        }

        $this->createCharge->execute([
            'encounter_id' => $encounter->getKey(),
            'service_id' => $service->getKey(),
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => $price,
            'source_type' => ChargeSource::Consultation->value,
            'source_id' => $encounter->getKey(),
        ]);
    }
}
