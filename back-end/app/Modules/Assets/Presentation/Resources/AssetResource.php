<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Resources;

use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ItAssetModel
 */
final class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hospital_id' => $this->hospital_id,
            'asset_code' => $this->asset_code,
            'barcode' => $this->barcode,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'brand' => $this->brand,
            'model_name' => $this->model_name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'location' => $this->location,
            'received_by' => $this->received_by,
            'delivered_on' => $this->delivered_on?->toDateString(),
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'department' => $this->department === null ? null : [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ],
        ];
    }
}
