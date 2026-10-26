<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Pharmacy\Infrastructure\Persistence\Models\DrugCatalogueItemModel
 */
final class DrugCatalogueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_record_id' => $this->source_record_id,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'generic_name' => $this->generic_name,
            'strength' => $this->strength,
            'dosage_form' => $this->dosage_form,
            'pack' => $this->pack,
            'company' => $this->company,
            'company_ar' => $this->company_ar,
            'company_label' => $this->companyLabel(),
            'product_type' => $this->product_type,
            'product_family' => $this->product_family,
            'registration_status' => $this->registration_status,
            'published_category' => $this->published_category,
            'stock' => $this->when(
                $this->relationLoaded('shelf'),
                fn () => $this->shelf === null ? null : [
                    'medication_id' => $this->shelf->id,
                    'quantity' => $this->shelf->quantity,
                    'price' => $this->shelf->price,
                    'barcode' => $this->shelf->barcode,
                ],
            ),
        ];
    }
}
