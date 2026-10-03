<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Billing\Infrastructure\Persistence\Models\PaymentModel
 */
final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'amount' => $this->amount,
            'refunded_amount' => $this->refundedAmount(),
            'net_amount' => $this->netAmount(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'reference' => $this->reference,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'notes' => $this->notes,
            'invoice_id' => $this->invoice_id,
            'received_by' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy?->displayName()),
        ];
    }
}
