<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceInstallmentPlanModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Splits whatever is still owed on an issued invoice into dated installments.
 * Money already collected stays as it is; only the open balance is scheduled.
 */
final class CreateInstallmentPlanAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly SyncInstallmentsAction $sync,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(InvoiceModel $invoice, array $data): InvoiceModel
    {
        $count = (int) $data['count'];
        $intervalMonths = (int) ($data['interval_months'] ?? 1);
        $firstDue = Carbon::parse((string) $data['first_due_date'])->startOfDay();

        return DB::transaction(function () use ($invoice, $count, $intervalMonths, $firstDue): InvoiceModel {
            $locked = InvoiceModel::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if (! $locked->status->acceptsPayment()) {
                throw BusinessRuleException::make('لا يمكن تقسيط فاتورة غير صادرة.');
            }

            $balance = round((float) $locked->balance, 2);

            if ($balance <= 0.009) {
                throw BusinessRuleException::make('لا يوجد رصيد لتقسيطه.');
            }

            if ($balance + 0.001 < $count * 0.01) {
                throw BusinessRuleException::make('المبلغ أصغر من أن يُقسَّم على هذا العدد من الأقساط.');
            }

            $existing = $locked->installmentPlan()->lockForUpdate()->first();

            if ($existing !== null) {
                $collected = round(max(0, (float) $locked->paid_amount - (float) $existing->baseline_paid), 2);

                if ($collected > 0.009) {
                    throw BusinessRuleException::make('بدأ تحصيل الأقساط، ولا يمكن إعادة جدولتها.');
                }

                $existing->delete();
            }

            $plan = InvoiceInstallmentPlanModel::query()->create([
                'invoice_id' => $locked->getKey(),
                'baseline_paid' => $locked->paid_amount,
                'created_by' => $this->currentUser->id(),
            ]);

            $cents = (int) round($balance * 100);
            $base = intdiv($cents, $count);
            $remainder = $cents - ($base * $count);

            for ($index = 0; $index < $count; $index++) {
                $share = $base + ($index === $count - 1 ? $remainder : 0);

                $plan->installments()->create([
                    'invoice_id' => $locked->getKey(),
                    'sequence' => $index + 1,
                    'amount' => $share / 100,
                    'due_date' => $firstDue->copy()->addMonths($index * $intervalMonths)->toDateString(),
                    'paid_amount' => 0,
                    'status' => 'scheduled',
                ]);
            }

            $this->sync->execute($locked);

            return $locked->fresh(['items', 'payments', 'patient', 'installments']);
        });
    }
}
