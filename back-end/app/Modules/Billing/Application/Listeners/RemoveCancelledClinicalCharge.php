<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\RemoveSourceChargesAction;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Laboratory\Domain\Events\BloodRequestCancelled;
use App\Modules\Laboratory\Domain\Events\LabOrderItemCancelled;
use App\Modules\Radiology\Domain\Enums\RadiologyItemStatus;
use App\Modules\Radiology\Domain\Events\RadiologyOrderItemCancelled;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;

/**
 * A cancelled laboratory test, imaging study or blood request leaves the
 * patient account. The charge is removed whether or not it was invoiced.
 */
final class RemoveCancelledClinicalCharge
{
    public function __construct(private readonly RemoveSourceChargesAction $remove) {}

    public function handle(LabOrderItemCancelled|RadiologyOrderItemCancelled|BloodRequestCancelled $event): void
    {
        if ($event instanceof LabOrderItemCancelled) {
            $this->remove->execute(ChargeSource::LabOrderItem->value, $event->item->getKey());

            return;
        }

        if ($event instanceof BloodRequestCancelled) {
            $this->remove->execute(ChargeSource::BloodRequest->value, $event->request->getKey());

            return;
        }

        $item = $event->item;
        $this->remove->execute(ChargeSource::RadiologyOrderItem->value, $item->getKey());

        $orderId = (int) $item->radiology_order_id;
        $stillActive = RadiologyOrderItemModel::query()
            ->where('radiology_order_id', $orderId)
            ->where('status', '!=', RadiologyItemStatus::Cancelled->value)
            ->exists();

        if (! $stillActive) {
            $this->remove->execute(ChargeSource::RadiologyInjection->value, $orderId);
        }
    }
}
