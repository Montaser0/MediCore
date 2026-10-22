<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Laboratory\Infrastructure\Persistence\Models\LabResultModel
 */
final class LabResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_order_item_id' => $this->lab_order_item_id,
            'result_value' => $this->result_value,
            'unit' => $this->unit,
            'reference_range' => $this->reference_range,
            'flag' => $this->flag?->value,
            'comment' => $this->comment,
            'status' => $this->status->value,
            'is_verified' => $this->isVerified(),
            'entered_at' => $this->entered_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'entered_by' => $this->whenLoaded('enteredBy', fn () => $this->enteredBy?->displayName()),
            'verified_by' => $this->whenLoaded('verifiedBy', fn () => $this->verifiedBy?->displayName()),
            'values' => $this->whenLoaded('values', fn () => $this->values->map(fn ($value) => [
                'id' => $value->id,
                'parameter' => $value->parameter?->name,
                'parameter_id' => $value->lab_test_parameter_id,
                'value' => $value->value,
                'unit' => $value->unit,
                'reference_range' => $value->reference_range,
                'flag' => $value->flag?->value,
            ])->all()),
        ];
    }
}
