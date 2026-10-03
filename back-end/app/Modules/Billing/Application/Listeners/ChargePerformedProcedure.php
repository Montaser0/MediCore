<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Billing\Infrastructure\Persistence\Models\ServiceModel;
use App\Modules\Clinical\Domain\Events\ProcedurePerformed;

/**
 * A recorded procedure is billed from the service it was picked from.
 * A free-text procedure with no price stays clinical only.
 */
final class ChargePerformedProcedure
{
    public function __construct(private readonly CreateChargeAction $createCharge) {}

    public function handle(ProcedurePerformed $event): void
    {
        if (! config('hms.billing.auto_charge', true)) {
            return;
        }

        $procedure = $event->procedure;

        $service = $procedure->service_id
            ? ServiceModel::query()->find($procedure->service_id)
            : ($procedure->code
                ? ServiceModel::query()->where('code', $procedure->code)->first()
                : null);

        $price = (float) ($service?->price ?? 0);

        if ($price <= 0) {
            return;
        }

        $this->createCharge->execute([
            'encounter_id' => (int) $procedure->encounter_id,
            'service_id' => $service?->getKey(),
            'description' => $service?->name ?? $procedure->name,
            'quantity' => 1,
            'unit_price' => $price,
            'source_type' => ChargeSource::Procedure->value,
            'source_id' => $procedure->getKey(),
        ]);
    }
}
