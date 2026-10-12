<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Queries;

use App\Modules\Billing\Infrastructure\Persistence\Models\ChargeModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\PatientInsuranceModel;

/**
 * The patient's financial account: what has been charged, what has been
 * invoiced, what is still owed and what insurance covers.
 */
final class GetPatientAccountQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $patientId): array
    {
        $charges = ChargeModel::query()->where('patient_id', $patientId);
        $invoices = InvoiceModel::query()->where('patient_id', $patientId);

        $uninvoicedTotal = (float) (clone $charges)->uninvoiced()->sum('total');
        $invoicedTotal = (float) (clone $invoices)->sum('total');
        $paidTotal = (float) (clone $invoices)->sum('paid_amount');
        $outstanding = (float) (clone $invoices)->sum('balance');

        $uninvoiced = (clone $charges)
            ->uninvoiced()
            ->select([
                'id', 'description', 'quantity', 'unit_price', 'discount', 'total',
                'source_type', 'encounter_id', 'created_at',
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $recentInvoices = (clone $invoices)
            ->select([
                'id', 'invoice_number', 'status', 'total', 'paid_amount', 'balance', 'issued_at', 'created_at',
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $insurance = PatientInsuranceModel::query()
            ->with('company')
            ->where('patient_id', $patientId)
            ->validNow()
            ->orderByDesc('is_primary')
            ->get();

        return [
            'totals' => [
                'uninvoiced_charges' => round($uninvoicedTotal, 2),
                'invoiced_total' => round($invoicedTotal, 2),
                'paid_total' => round($paidTotal, 2),
                'outstanding_balance' => round($outstanding, 2),
            ],
            'uninvoiced_charges' => $uninvoiced->map(fn (ChargeModel $charge) => [
                'id' => $charge->getKey(),
                'description' => $charge->description,
                'quantity' => $charge->quantity,
                'unit_price' => $charge->unit_price,
                'discount' => $charge->discount,
                'total' => $charge->total,
                'source_type' => $charge->source_type,
                'encounter_id' => $charge->encounter_id,
                'created_at' => $charge->created_at?->toIso8601String(),
            ])->all(),
            'invoices' => $recentInvoices->map(fn (InvoiceModel $invoice) => [
                'id' => $invoice->getKey(),
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'total' => $invoice->total,
                'paid_amount' => $invoice->paid_amount,
                'balance' => $invoice->balance,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
            ])->all(),
            'insurance' => $insurance->map(fn (PatientInsuranceModel $policy) => [
                'id' => $policy->getKey(),
                'company' => $policy->company?->name,
                'policy_number' => $policy->policy_number,
                'coverage_percentage' => $policy->coverage_percentage,
                'valid_to' => $policy->valid_to?->toDateString(),
                'is_primary' => $policy->is_primary,
            ])->all(),
        ];
    }
}
