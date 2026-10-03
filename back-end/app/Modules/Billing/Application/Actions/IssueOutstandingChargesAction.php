<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Billing\Infrastructure\Persistence\Models\ChargeModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use Illuminate\Support\Facades\DB;

/**
 * Turns every charge still sitting on a visit into one issued invoice.
 * Used when the visit closes, and again if a result arrives afterwards.
 */
final class IssueOutstandingChargesAction
{
    public function __construct(private readonly CreateInvoiceAction $createInvoice) {}

    public function execute(int $encounterId): ?InvoiceModel
    {
        return DB::transaction(function () use ($encounterId): ?InvoiceModel {
            $ids = ChargeModel::query()
                ->uninvoiced()
                ->where('encounter_id', $encounterId)
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isEmpty()) {
                return null;
            }

            return $this->createInvoice->execute([
                'charge_ids' => $ids->all(),
                'encounter_id' => $encounterId,
                'issue' => true,
            ]);
        });
    }
}
