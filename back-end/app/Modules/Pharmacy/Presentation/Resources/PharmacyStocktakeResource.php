<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Resources;

use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyStocktakeLineModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyStocktakeModel
 */
final class PharmacyStocktakeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stocktake_number' => $this->stocktake_number,
            'status' => $this->status,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines
                ->sortBy('name')
                ->values()
                ->map(function (PharmacyStocktakeLineModel $line): array {
                    $system = $line->system_quantity === null ? null : (float) $line->system_quantity;
                    $counted = $line->counted_quantity === null ? null : (float) $line->counted_quantity;

                    return [
                        'id' => $line->id,
                        'medication_id' => $line->medication_id,
                        'name' => $line->name,
                        'barcode' => $line->barcode,
                        'system_quantity' => $system,
                        'counted_quantity' => $counted,
                        'variance' => $system === null || $counted === null ? null : round($counted - $system, 2),
                    ];
                })->all()),
        ];
    }
}
