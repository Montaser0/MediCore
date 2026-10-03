<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Billing\Application\Actions\ApplyDiscountAction;
use App\Modules\Billing\Application\Actions\CreateInstallmentPlanAction;
use App\Modules\Billing\Application\Actions\CreateInvoiceAction;
use App\Modules\Billing\Application\Queries\GetPatientAccountQuery;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use App\Modules\Billing\Presentation\Requests\ApplyDiscountRequest;
use App\Modules\Billing\Presentation\Requests\StoreInstallmentPlanRequest;
use App\Modules\Billing\Presentation\Requests\StoreInvoiceRequest;
use App\Modules\Billing\Presentation\Resources\InvoiceResource;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvoiceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $invoices = InvoiceModel::query()
            ->with('patient')
            ->forCurrentHospital()
            ->when($request->filled('patient_id'), fn ($query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('status'), fn ($query) => $query->whereIn('status', (array) $request->input('status')))
            ->when($request->boolean('outstanding_only'), fn ($query) => $query->outstanding())
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $request->date('to')))
            ->orderByDesc('created_at')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(InvoiceResource::collection($invoices), $invoices);
    }

    public function show(InvoiceModel $invoice): JsonResponse
    {
        return ApiResponse::resource(new InvoiceResource($invoice->load([
            'items', 'payments.receivedBy', 'patient', 'encounter', 'installments', 'hospital',
        ])));
    }

    /**
     * The patient's financial account, which is what the cashier opens.
     */
    public function patientAccount(PatientModel $patient, GetPatientAccountQuery $query): JsonResponse
    {
        return ApiResponse::data($query->execute($patient->getKey()));
    }

    public function store(StoreInvoiceRequest $request, CreateInvoiceAction $action): JsonResponse
    {
        return ApiResponse::created(new InvoiceResource($action->execute($request->validated())));
    }

    public function issue(InvoiceModel $invoice): JsonResponse
    {
        if ($invoice->status->canTransitionTo(InvoiceStatus::Issued)) {
            $invoice->forceFill([
                'status' => InvoiceStatus::Issued->value,
                'issued_at' => now(),
            ])->save();
        }

        return ApiResponse::resource(new InvoiceResource($invoice->load('items')));
    }

    public function applyDiscount(ApplyDiscountRequest $request, InvoiceModel $invoice, ApplyDiscountAction $action): JsonResponse
    {
        $updated = $action->execute(
            $invoice,
            (float) $request->input('discount'),
            $request->string('reason')->toString(),
        );

        return ApiResponse::resource(new InvoiceResource($updated->load('items')));
    }

    public function installments(
        StoreInstallmentPlanRequest $request,
        InvoiceModel $invoice,
        CreateInstallmentPlanAction $action,
    ): JsonResponse {
        return ApiResponse::resource(new InvoiceResource(
            $action->execute($invoice, $request->validated())
        ));
    }

    public function cancel(Request $request, InvoiceModel $invoice): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        if (! $invoice->status->canTransitionTo(InvoiceStatus::Cancelled)) {
            return ApiResponse::message(
                "لا يمكن إلغاء الفاتورة {$invoice->invoice_number} لأنها «{$invoice->status->label()}».",
                422,
            );
        }

        // Cancelling releases the charges so they can be billed correctly.
        $invoice->charges()->update(['invoice_id' => null]);

        $invoice->forceFill([
            'status' => InvoiceStatus::Cancelled->value,
            'notes' => trim(($invoice->notes ?? '')."\nCancelled: ".$request->string('reason')->toString()),
        ])->save();

        return ApiResponse::resource(new InvoiceResource($invoice->load('items')));
    }
}
