<?php

declare(strict_types=1);

namespace App\Modules\Reports\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Billing\Infrastructure\Persistence\Models\PaymentModel;
use App\Modules\Inventory\Application\Queries\StockAlertsQuery;
use App\Modules\Reports\Application\Queries\ActivityReportQuery;
use App\Modules\Reports\Application\Queries\DashboardQuery;
use App\Modules\Reports\Application\Queries\FinancialReportQuery;
use App\Modules\Reports\Application\Services\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends ApiController
{
    public function dashboard(DashboardQuery $query): JsonResponse
    {
        return ApiResponse::data($query->execute());
    }

    public function financial(Request $request, FinancialReportQuery $query): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::data($query->execute($from, $to));
    }

    public function activity(Request $request, ActivityReportQuery $query): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::data($query->execute($from, $to));
    }

    public function inventory(StockAlertsQuery $query): JsonResponse
    {
        return ApiResponse::data($query->execute());
    }

    /**
     * The cashier's end-of-day reconciliation: every receipt taken in the
     * period, in the order it was taken.
     */
    public function exportPayments(Request $request, ReportExportService $exporter): StreamedResponse
    {
        [$from, $to] = $this->period($request);

        $rows = PaymentModel::query()
            ->with(['invoice.patient', 'receivedBy'])
            ->forCurrentHospital()
            ->whereBetween('paid_at', [$from, $to])
            ->orderBy('paid_at')
            ->get()
            ->map(fn (PaymentModel $payment) => [
                'receipt_number' => $payment->receipt_number,
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                'invoice_number' => $payment->invoice?->invoice_number,
                'patient_mrn' => $payment->invoice?->patient?->mrn,
                'patient_name' => $payment->invoice?->patient?->full_name,
                'method' => $payment->method->value,
                'amount' => $payment->amount,
                'refunded' => $payment->refundedAmount(),
                'net' => $payment->netAmount(),
                'received_by' => $payment->receivedBy?->displayName(),
            ])
            ->all();

        return $exporter->csv('payments', $rows);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : now()->startOfMonth(),
            $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : now(),
        ];
    }
}
