<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Infrastructure\Persistence\Models\ChargeModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceItemModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use Illuminate\Support\Facades\DB;

/**
 * Drops the account lines that were raised for one clinical row, including
 * the copy already placed on an invoice.
 */
final class RemoveSourceChargesAction
{
    public function __construct(private readonly RecalculateInvoiceAction $recalculate) {}

    public function execute(string $sourceType, int $sourceId): void
    {
        $charges = ChargeModel::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->get();

        if ($charges->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($charges): void {
            $invoiceIds = [];

            foreach ($charges as $charge) {
                if ($charge->invoice_id !== null) {
                    $invoiceIds[] = (int) $charge->invoice_id;
                    InvoiceItemModel::query()->where('charge_id', $charge->getKey())->delete();
                }

                $charge->delete();
            }

            foreach (array_unique($invoiceIds) as $invoiceId) {
                $invoice = InvoiceModel::query()->find($invoiceId);

                if ($invoice === null) {
                    continue;
                }

                $invoice->unsetRelation('items');
                $invoice->unsetRelation('payments');
                $this->recalculate->execute($invoice);
                $this->syncStatus($invoice->fresh());
                $this->fitInstallments($invoice->fresh());
            }
        });
    }

    private function syncStatus(InvoiceModel $invoice): void
    {
        $total = (float) $invoice->total;
        $paid = (float) $invoice->paid_amount;
        $empty = $invoice->items()->count() === 0;

        $status = match (true) {
            $empty && $paid <= 0.009 => InvoiceStatus::Cancelled,
            $paid <= 0.009 => $invoice->status === InvoiceStatus::Draft ? InvoiceStatus::Draft : InvoiceStatus::Issued,
            $paid + 0.009 >= $total => InvoiceStatus::Paid,
            default => InvoiceStatus::PartiallyPaid,
        };

        $invoice->forceFill(['status' => $status->value])->save();
    }

    private function fitInstallments(InvoiceModel $invoice): void
    {
        $rows = $invoice->installments()->orderBy('sequence')->get();

        if ($rows->isEmpty()) {
            return;
        }

        $excess = round((float) $rows->sum('amount') - (float) $invoice->total, 2);

        foreach ($rows->reverse() as $row) {
            if ($excess <= 0.009) {
                break;
            }

            $reducible = max(0, (float) $row->amount - (float) $row->paid_amount);
            $cut = min($reducible, $excess);

            $row->forceFill(['amount' => round((float) $row->amount - $cut, 2)])->save();
            $excess = round($excess - $cut, 2);
        }
    }
}
