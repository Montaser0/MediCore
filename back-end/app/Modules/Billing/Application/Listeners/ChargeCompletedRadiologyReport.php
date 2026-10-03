<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Radiology\Domain\Events\ReportCompleted;

/**
 * Imaging is billed when the report is released, which is the point the
 * service has actually been delivered.
 */
final class ChargeCompletedRadiologyReport
{
    public function __construct(private readonly CreateChargeAction $createCharge) {}

    public function handle(ReportCompleted $event): void
    {
        if (! config('hms.billing.auto_charge', true)) {
            return;
        }

        $report = $event->report;
        $report->loadMissing(['study.orderItem.service', 'study.radiologyOrder.order']);

        $item = $report->study?->orderItem;
        $order = $report->study?->radiologyOrder?->order;

        if ($item === null || $order === null) {
            return;
        }

        $price = (float) ($item->service?->price ?? 0);

        if ($price <= 0) {
            return;
        }

        $this->createCharge->execute([
            'encounter_id' => $order->encounter_id,
            'description' => $item->service?->name ?? 'فحص أشعة',
            'quantity' => 1,
            'unit_price' => $price,
            'source_type' => ChargeSource::RadiologyOrderItem->value,
            'source_id' => $item->getKey(),
        ]);
    }
}
