<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyReturnModel
 */
final class PharmacyReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'sale_id' => $this->pharmacy_sale_id,
            'total' => $this->total,
            'cash_refund' => $this->cash_refund,
            'debt_reduction' => round((float) $this->total - (float) $this->cash_refund, 2),
            'reason' => $this->reason,
            'returned_at' => $this->returned_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'name' => $item->name,
                'barcode' => $item->barcode,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->all()),
        ];
    }
}
