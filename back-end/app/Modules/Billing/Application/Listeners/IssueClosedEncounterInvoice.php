<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Application\Actions\IssueOutstandingChargesAction;
use App\Modules\Billing\Domain\Events\ChargeRaised;
use App\Modules\Encounters\Domain\Events\EncounterCompleted;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;

/**
 * The visit close is the bill. Anything delivered after that — a late
 * laboratory verification, for example — is invoiced on its own.
 */
final class IssueClosedEncounterInvoice
{
    public function __construct(private readonly IssueOutstandingChargesAction $issue) {}

    public function handle(EncounterCompleted|ChargeRaised $event): void
    {
        if (! config('hms.billing.auto_charge', true)) {
            return;
        }

        if ($event instanceof ChargeRaised) {
            $encounter = EncounterModel::query()->find($event->charge->encounter_id);

            if ($encounter === null || $encounter->status->isOpen()) {
                return;
            }

            $this->issue->execute((int) $encounter->getKey());

            return;
        }

        $this->issue->execute((int) $event->encounter->getKey());
    }
}
