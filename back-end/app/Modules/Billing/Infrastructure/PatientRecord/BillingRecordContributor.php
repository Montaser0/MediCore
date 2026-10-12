<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Billing\Infrastructure\Persistence\Models\ChargeModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;

final class BillingRecordContributor implements PatientRecordContributor
{
    public function section(): string
    {
        return 'billing';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $charges = ChargeModel::query()
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId));

        $invoices = InvoiceModel::query()
            ->with(['installments', 'createdBy'])
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId));

        return [
            'charged_total' => round((float) (clone $charges)->sum('total'), 2),
            'uninvoiced_total' => round((float) (clone $charges)->uninvoiced()->sum('total'), 2),
            'invoiced_total' => round((float) (clone $invoices)->sum('total'), 2),
            'paid_total' => round((float) (clone $invoices)->sum('paid_amount'), 2),
            'outstanding_balance' => round((float) (clone $invoices)->sum('balance'), 2),
            'recent_charges' => (clone $charges)
                ->with('createdBy')
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->map(fn (ChargeModel $charge) => [
                    'id' => $charge->getKey(),
                    'description' => $charge->description,
                    'quantity' => $charge->quantity,
                    'unit_price' => $charge->unit_price,
                    'total' => $charge->total,
                    'source_type' => $charge->source_type,
                    'by' => $charge->createdBy?->displayName(),
                ])
                ->all(),
            'recent_invoices' => (clone $invoices)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (InvoiceModel $invoice) => [
                    'id' => $invoice->getKey(),
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status->value,
                    'total' => $invoice->total,
                    'paid_amount' => $invoice->paid_amount,
                    'balance' => $invoice->balance,
                    'issued_at' => $invoice->issued_at?->toIso8601String(),
                    'by' => $invoice->createdBy?->displayName(),
                    'installments' => $invoice->installments
                        ->filter(fn ($row) => (float) $row->amount > 0.009)
                        ->map(fn ($row) => $row->toSummary())
                        ->values()
                        ->all(),
                ])
                ->all(),
        ];
    }
}
