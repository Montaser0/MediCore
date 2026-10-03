<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Laboratory\Domain\Events\LabResultVerified;

/**
 * A laboratory test is billed once its result is verified, so a cancelled or
 * repeated test never reaches the patient's bill.
 */
final class ChargeVerifiedLabResult
{
    public function __construct(private readonly CreateChargeAction $createCharge) {}

    public function handle(LabResultVerified $event): void
    {
        if (! config('hms.billing.auto_charge', true)) {
            return;
        }

        $item = $event->result->orderItem;
        $order = $item?->labOrder?->order;

        if ($item === null || $order === null) {
            return;
        }

        $price = (float) ($item->test?->price ?? 0);

        if ($price <= 0) {
            return;
        }

        $this->createCharge->execute([
            'encounter_id' => $order->encounter_id,
            'description' => $item->test?->name ?? 'تحليل مخبري',
            'quantity' => 1,
            'unit_price' => $price,
            'source_type' => ChargeSource::LabOrderItem->value,
            'source_id' => $item->getKey(),
        ]);
    }
}
