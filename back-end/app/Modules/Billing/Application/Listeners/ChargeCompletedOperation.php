<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Operations\Domain\Events\OperationCompleted;

/**
 * The operation itself is charged at its list price; materials are charged
 * at cost, because implants and consumables vary wildly between cases.
 */
final class ChargeCompletedOperation
{
    public function __construct(private readonly CreateChargeAction $createCharge) {}

    public function handle(OperationCompleted $event): void
    {
        if (! config('hms.billing.auto_charge', true)) {
            return;
        }

        $operation = $event->operation;
        $operation->loadMissing(['type', 'materials.item']);

        $list = (float) ($operation->type?->price ?? 0);
        $addition = (float) ($operation->addition ?? 0);
        $discount = (float) ($operation->discount ?? 0);
        $gross = round($list + $addition, 2);

        if ($gross > 0) {
            $this->createCharge->execute([
                'encounter_id' => $operation->encounter_id,
                'description' => $operation->type?->name ?? 'عملية',
                'quantity' => 1,
                'unit_price' => $gross,
                'discount' => min($discount, $gross),
                'source_type' => ChargeSource::Operation->value,
                'source_id' => $operation->getKey(),
            ]);
        }

        foreach ($operation->materials as $material) {
            if ((float) $material->unit_cost <= 0) {
                continue;
            }

            $this->createCharge->execute([
                'encounter_id' => $operation->encounter_id,
                'description' => 'مستلزم جراحي — '.($material->item?->name ?? 'صنف'),
                'quantity' => $material->quantity,
                'unit_price' => $material->unit_cost,
                'source_type' => ChargeSource::Supply->value,
                'source_id' => $material->getKey(),
            ]);
        }
    }
}
