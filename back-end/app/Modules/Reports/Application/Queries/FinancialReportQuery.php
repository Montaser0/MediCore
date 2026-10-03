<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application\Queries;

use App\Modules\Billing\Domain\Enums\PaymentMethod;
use App\Modules\Billing\Infrastructure\Persistence\Models\ChargeModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\PaymentModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\RefundModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Revenue for a period, broken down the three ways finance always asks for:
 * by day, by payment method, and by what was actually sold.
 */
final class FinancialReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $payments = PaymentModel::query()->forCurrentHospital()->whereBetween('paid_at', [$from, $to]);
        $refunds = RefundModel::query()
            ->whereBetween('refunded_at', [$from, $to])
            ->whereHas('payment', fn ($query) => $query->forCurrentHospital());

        $collected = (float) (clone $payments)->sum('amount');
        $refunded = (float) (clone $refunds)->sum('amount');

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'charged' => round((float) ChargeModel::query()->forCurrentHospital()->whereBetween('created_at', [$from, $to])->sum('total'), 2),
                'invoiced' => round((float) InvoiceModel::query()->forCurrentHospital()->whereBetween('created_at', [$from, $to])->sum('total'), 2),
                'discounts' => round((float) InvoiceModel::query()->forCurrentHospital()->whereBetween('created_at', [$from, $to])->sum('discount'), 2),
                'collected' => round($collected, 2),
                'refunded' => round($refunded, 2),
                'net_revenue' => round($collected - $refunded, 2),
                'outstanding' => round((float) InvoiceModel::query()->forCurrentHospital()->outstanding()->sum('balance'), 2),
            ],
            'by_day' => (clone $payments)
                ->selectRaw('DATE(paid_at) as day, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($row) => [
                    'day' => (string) $row->day,
                    'total' => round((float) $row->total, 2),
                    'count' => (int) $row->count,
                ])
                ->all(),
            'by_method' => (clone $payments)
                ->selectRaw('method, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('method')
                ->get()
                ->map(fn ($row) => [
                    'method' => $row->method instanceof PaymentMethod ? $row->method->value : (string) $row->method,
                    'total' => round((float) $row->total, 2),
                    'count' => (int) $row->count,
                ])
                ->all(),
            'by_service_source' => ChargeModel::query()
                ->forCurrentHospital()
                ->whereBetween('created_at', [$from, $to])
                ->select('source_type', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as count'))
                ->groupBy('source_type')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'source_type' => (string) $row->source_type,
                    'total' => round((float) $row->total, 2),
                    'count' => (int) $row->count,
                ])
                ->all(),
        ];
    }
}
