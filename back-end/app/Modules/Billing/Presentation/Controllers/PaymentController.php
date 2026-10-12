<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Core\Persistence\QueryWindow;
use App\Modules\Billing\Application\Actions\ReceivePaymentAction;
use App\Modules\Billing\Application\Actions\RefundPaymentAction;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\PaymentModel;
use App\Modules\Billing\Presentation\Requests\StorePaymentRequest;
use App\Modules\Billing\Presentation\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $payments = PaymentModel::query()
            ->select([
                'payments.id',
                'payments.hospital_id',
                'payments.invoice_id',
                'payments.receipt_number',
                'payments.amount',
                'payments.method',
                'payments.reference',
                'payments.received_by',
                'payments.paid_at',
                'payments.notes',
            ])
            ->with([
                'receivedBy:id,username,staff_id',
                'receivedBy.staff:id,first_name,last_name',
                'refunds:id,payment_id,amount',
            ])
            ->forCurrentHospital()
            ->when($request->filled('invoice_id'), fn ($query) => $query->where('payments.invoice_id', $request->integer('invoice_id')))
            ->when($request->filled('method'), fn ($query) => $query->whereIn('payments.method', (array) $request->input('method')))
            ->when($request->filled('date'), fn ($query) => QueryWindow::on($query, 'payments.paid_at', $request->date('date')))
            ->when($request->filled('from'), fn ($query) => QueryWindow::from($query, 'payments.paid_at', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => QueryWindow::until($query, 'payments.paid_at', $request->date('to')))
            ->orderByDesc('payments.paid_at')
            ->paginate(min($this->perPage(25), 50));

        return ApiResponse::paginated(PaymentResource::collection($payments), $payments);
    }

    public function store(StorePaymentRequest $request, InvoiceModel $invoice, ReceivePaymentAction $action): JsonResponse
    {
        return ApiResponse::created(new PaymentResource(
            $action->execute($invoice, $request->validated())->load('receivedBy')
        ));
    }

    public function refund(Request $request, PaymentModel $payment, RefundPaymentAction $action): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $refund = $action->execute($payment, (float) $validated['amount'], $validated['reason']);

        return ApiResponse::data([
            'refund' => [
                'id' => $refund->getKey(),
                'amount' => $refund->amount,
                'reason' => $refund->reason,
                'refunded_at' => $refund->refunded_at?->toIso8601String(),
            ],
            'payment' => (new PaymentResource($payment->fresh(['refunds', 'receivedBy'])))->resolve(),
        ]);
    }
}
