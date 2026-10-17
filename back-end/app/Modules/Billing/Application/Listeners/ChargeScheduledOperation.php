<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Operations\Domain\Events\OperationScheduled;

/**
 * The clinic price is posted when the operation is added to the visit.
 * Completion does not bill it again; cancellation takes the line back off.
 */
final class ChargeScheduledOperation
{
    public function __construct(private readonly CreateChargeAction $createCharge) {}

    public function handle(OperationScheduled $event): void
    {
        if (! config('hms.billing.auto_charge', true)) {
            return;
        }

        $operation = $event->operation;
        $operation->loadMissing('type');
        $list = (float) ($operation->type?->price ?? 0);
        $addition = (float) ($operation->addition ?? 0);
        $discount = (float) ($operation->discount ?? 0);
        $gross = round($list + $addition, 2);

        if ($gross <= 0) {
            return;
        }

        $this->createCharge->execute([
            'encounter_id' => (int) $operation->encounter_id,
            'description' => $operation->type?->name ?? 'عملية',
            'quantity' => 1,
            'unit_price' => $gross,
            'discount' => $discount,
            'source_type' => ChargeSource::Operation->value,
            'source_id' => $operation->getKey(),
        ]);
    }
}
