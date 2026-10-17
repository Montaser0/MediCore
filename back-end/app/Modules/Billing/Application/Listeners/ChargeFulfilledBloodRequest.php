<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Laboratory\Domain\Events\BloodRequestCreated;
use App\Modules\Laboratory\Domain\Events\BloodRequestFulfilled;

/**
 * Blood is billed when it is requested, at the product price saved on the
 * request. Confirming the bag later does not add a second line.
 */
final class ChargeFulfilledBloodRequest
{
    public function __construct(private readonly CreateChargeAction $createCharge) {}

    public function handle(BloodRequestCreated|BloodRequestFulfilled $event): void
    {
        if (! config('hms.billing.auto_charge', true)) {
            return;
        }

        $request = $event->request;
        $price = (float) $request->unit_price;

        if ($price <= 0 || $request->encounter_id === null) {
            return;
        }

        $this->createCharge->execute([
            'encounter_id' => $request->encounter_id,
            'description' => $request->product_name ?: $request->product_type,
            'quantity' => $request->units,
            'unit_price' => $price,
            'source_type' => ChargeSource::BloodRequest->value,
            'source_id' => $request->getKey(),
        ]);
    }
}
