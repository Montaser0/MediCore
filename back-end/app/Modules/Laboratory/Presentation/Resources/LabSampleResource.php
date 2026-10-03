<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Laboratory\Infrastructure\Persistence\Models\LabSampleModel
 */
final class LabSampleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sample_number' => $this->sample_number,
            'sample_type' => $this->sample_type,
            'status' => $this->status->value,
            'collected_at' => $this->collected_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'collected_by' => $this->whenLoaded('collectedBy', fn () => $this->collectedBy?->displayName()),
        ];
    }
}
