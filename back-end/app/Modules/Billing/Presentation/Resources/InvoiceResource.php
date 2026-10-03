<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'balance' => $this->balance,
            'notes' => $this->notes,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'encounter_id' => $this->encounter_id,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'total' => $item->total,
                'charge_id' => $item->charge_id,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => PaymentResource::collection($this->payments)->resolve()),
            'installments' => $this->whenLoaded('installments', fn () => $this->installments
                ->filter(fn ($row) => (float) $row->amount > 0.009)
                ->map(fn ($row) => $row->toSummary())
                ->values()
                ->all()),
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'mrn' => $this->patient?->mrn,
                'full_name' => $this->patient?->full_name,
                'phone' => $this->patient?->phone,
                'national_id' => $this->patient?->national_id,
            ]),
            'encounter' => $this->whenLoaded('encounter', fn () => [
                'id' => $this->encounter?->id,
                'encounter_number' => $this->encounter?->encounter_number,
            ]),
            'hospital' => $this->whenLoaded('hospital', fn () => [
                'name' => $this->hospital?->name,
                'phone' => $this->hospital?->phone,
                'address' => $this->hospital?->address,
                'city' => $this->hospital?->city,
                'currency' => $this->hospital?->currency,
            ]),
        ];
    }
}
