<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyStudyModel
 */
final class RadiologyStudyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'study_number' => $this->study_number,
            'accession_number' => $this->accession_number,
            'study_instance_uid' => $this->study_instance_uid,
            'status' => $this->status->value,
            'image_count' => $this->image_count,
            'technician_notes' => $this->technician_notes,
            'performed_at' => $this->performed_at?->toIso8601String(),
            'performed_by' => $this->whenLoaded('performedBy', fn () => $this->performedBy?->displayName()),
            'report' => $this->whenLoaded('report', fn () => $this->report === null
                ? null
                : (new RadiologyReportResource($this->report))->resolve()),
        ];
    }
}
