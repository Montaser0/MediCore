<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Billing\Infrastructure\Persistence\Models\ServiceModel
 */
final class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'category' => $this->category,
            'price' => $this->price,
            'is_taxable' => $this->is_taxable,
            'is_active' => $this->is_active,
        ];
    }
}
