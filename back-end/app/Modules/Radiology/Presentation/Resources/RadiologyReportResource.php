<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyReportModel
 */
final class RadiologyReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'study_id' => $this->study_id,
            'findings' => $this->findings,
            'impression' => $this->impression,
            'is_critical' => $this->is_critical,
            'status' => $this->status->value,
            'is_final' => $this->isFinal(),
            'reported_at' => $this->reported_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'reported_by' => $this->whenLoaded('reportedBy', fn () => $this->reportedBy?->displayName()),
            'verified_by' => $this->whenLoaded('verifiedBy', fn () => $this->verifiedBy?->displayName()),
        ];
    }
}
