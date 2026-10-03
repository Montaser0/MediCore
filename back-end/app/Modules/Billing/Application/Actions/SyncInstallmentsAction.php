<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Billing\Domain\Enums\InstallmentStatus;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceInstallmentModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use Illuminate\Support\Collection;

/**
 * Keeps an installment plan aligned with the invoice. Receipts recorded
 * after the plan opened fill the installments in order, and a later
 * discount shrinks the unpaid tail so the plan never asks for more than
 * the balance.
 */
final class SyncInstallmentsAction
{
    public function execute(InvoiceModel $invoice): void
    {
        $plan = $invoice->installmentPlan()->lockForUpdate()->first();

        if ($plan === null) {
            return;
        }

        /** @var Collection<int, InvoiceInstallmentModel> $rows */
        $rows = $plan->installments()->orderBy('sequence')->lockForUpdate()->get();

        if ($rows->isEmpty()) {
            return;
        }

        $allocated = round(max(0, (float) $invoice->paid_amount - (float) $plan->baseline_paid), 2);
        $target = round($allocated + max(0, (float) $invoice->balance), 2);

        $this->fitAmounts($rows, $target);

        $left = $allocated;

        foreach ($rows as $row) {
            $amount = round((float) $row->amount, 2);

            if ($amount <= 0.009) {
                $row->forceFill([
                    'amount' => 0,
                    'paid_amount' => 0,
                    'status' => InstallmentStatus::Paid->value,
                ])->save();

                continue;
            }

            $applied = round(min($amount, max(0, $left)), 2);
            $left = round($left - $applied, 2);

            $status = $applied + 0.009 >= $amount
                ? InstallmentStatus::Paid
                : ($applied > 0.009 ? InstallmentStatus::Partial : InstallmentStatus::Scheduled);

            $row->forceFill([
                'amount' => $amount,
                'paid_amount' => $applied,
                'status' => $status->value,
            ])->save();
        }
    }

    /**
     * @param  Collection<int, InvoiceInstallmentModel>  $rows
     */
    private function fitAmounts(Collection $rows, float $target): void
    {
        $sum = round((float) $rows->sum(fn (InvoiceInstallmentModel $row) => (float) $row->amount), 2);
        $delta = round($target - $sum, 2);

        if (abs($delta) <= 0.009) {
            return;
        }

        if ($delta > 0) {
            $last = $rows->last();
            $last->amount = round((float) $last->amount + $delta, 2);

            return;
        }

        $reduce = round(-$delta, 2);

        for ($index = $rows->count() - 1; $index >= 0 && $reduce > 0.009; $index--) {
            $row = $rows[$index];
            $cut = min((float) $row->amount, $reduce);
            $row->amount = round((float) $row->amount - $cut, 2);
            $reduce = round($reduce - $cut, 2);
        }
    }
}
