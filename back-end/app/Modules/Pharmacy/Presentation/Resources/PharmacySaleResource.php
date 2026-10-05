<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleModel
 */
final class PharmacySaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_number' => $this->sale_number,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'cash_amount' => $this->cash_amount,
            'due_amount' => $this->due_amount,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ]),
            'sold_at' => $this->sold_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'medication_id' => $item->medication_id,
                'name' => $item->name,
                'barcode' => $item->barcode,
                'quantity' => $item->quantity,
                'returned_quantity' => $item->relationLoaded('returnItems')
                    ? round((float) $item->returnItems->sum('quantity'), 2)
                    : 0,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->all()),
        ];
    }
}
