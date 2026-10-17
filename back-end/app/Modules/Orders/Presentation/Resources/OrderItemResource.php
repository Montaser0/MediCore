<?php

declare(strict_types=1);

namespace App\Modules\Orders\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Orders\Infrastructure\Persistence\Models\OrderItemModel
 */
final class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_type' => $this->service_type,
            'service_id' => $this->service_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'instructions' => $this->instructions,
            'status' => $this->status?->value,
        ];
    }
}
