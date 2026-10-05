<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application\Queries;

use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyDebtPaymentModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyPurchaseModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyReturnItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyReturnModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySupplierPaymentModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Till figures only: what was sold, in what quantity, and for how much.
 * It never reads patients, visits, or invoices.
 */
final class PharmacyReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $today = now()->startOfDay();
        $salesToday = PharmacySaleModel::query()->forCurrentHospital()->where('sold_at', '>=', $today);
        $returnsToday = PharmacyReturnModel::query()->forCurrentHospital()->where('returned_at', '>=', $today);
        $paymentsToday = PharmacyDebtPaymentModel::query()->forCurrentHospital()->where('paid_at', '>=', $today);

        $week = now()->startOfWeek(Carbon::SATURDAY);
        $month = now()->startOfMonth();

        return [
            'generated_at' => now()->toIso8601String(),
            'sales_today' => (clone $salesToday)->count(),
            'items_today' => round((float) PharmacySaleItemModel::query()
                ->whereIn('pharmacy_sale_id', (clone $salesToday)->select('id'))
                ->sum('quantity') - (float) PharmacyReturnItemModel::query()
                ->whereIn('pharmacy_return_id', (clone $returnsToday)->select('id'))
                ->sum('quantity'), 2),
            'discount_today' => $this->discount($today),
            'discount_week' => $this->discount($week),
            'discount_month' => $this->discount($month),
            'returns_today' => round((float) (clone $returnsToday)->sum('total'), 2),
            'revenue_today' => $this->revenue($today),
            'revenue_week' => $this->revenue($week),
            'revenue_month' => $this->revenue($month),
            'payments_today' => $this->supplierPayments($today),
            'payments_week' => $this->supplierPayments($week),
            'payments_month' => $this->supplierPayments($month),
            'profit_today' => round($this->revenue($today) - $this->supplierPayments($today), 2),
            'profit_week' => round($this->revenue($week) - $this->supplierPayments($week), 2),
            'profit_month' => round($this->revenue($month) - $this->supplierPayments($month), 2),
            'cash_today' => round(
                (float) (clone $salesToday)->sum('cash_amount')
                + (float) (clone $paymentsToday)->sum('amount')
                - (float) (clone $returnsToday)->sum('cash_refund'),
                2,
            ),
            'debts_today' => round((float) (clone $salesToday)->sum(DB::raw('total - cash_amount')), 2),
            'outstanding_debt' => round((float) PharmacySaleModel::query()->forCurrentHospital()->sum('due_amount'), 2),
        ];
    }

    private function revenue(Carbon $from): float
    {
        $sales = PharmacySaleModel::query()->forCurrentHospital()->where('sold_at', '>=', $from);
        $returns = PharmacyReturnModel::query()->forCurrentHospital()->where('returned_at', '>=', $from);

        return round((float) (clone $sales)->sum('total') - (float) (clone $returns)->sum('total'), 2);
    }

    private function discount(Carbon $from): float
    {
        return round((float) PharmacySaleModel::query()
            ->forCurrentHospital()
            ->where('sold_at', '>=', $from)
            ->sum('discount'), 2);
    }

    private function supplierPayments(Carbon $from): float
    {
        $atPurchase = (float) PharmacyPurchaseModel::query()
            ->forCurrentHospital()
            ->where('received_at', '>=', $from)
            ->sum('cash_amount');
        $later = (float) PharmacySupplierPaymentModel::query()
            ->forCurrentHospital()
            ->where('paid_at', '>=', $from)
            ->sum('amount');

        return round($atPurchase + $later, 2);
    }
}
