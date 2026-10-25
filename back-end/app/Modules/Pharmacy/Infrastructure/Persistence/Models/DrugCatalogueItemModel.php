<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class DrugCatalogueItemModel extends BaseModel
{
    protected $table = 'drug_catalogue_items';

    public function shelf(): HasOne
    {
        return $this->hasOne(MedicationModel::class, 'catalogue_item_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $inner) use ($term, $like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('generic_name', 'like', $like)
                ->orWhere('company_ar', 'like', $like)
                ->orWhere('company', 'like', $like)
                ->orWhere('strength', 'like', $like)
                ->orWhere('dosage_form', 'like', $like)
                ->orWhere('source_record_id', $term);
        });
    }

    public function companyLabel(): string
    {
        $arabic = trim((string) $this->company_ar);
        if ($arabic !== '') {
            return $arabic;
        }

        return trim((string) $this->company);
    }

    public function displayName(): string
    {
        $title = trim(implode(' ', array_filter([
            $this->name,
            $this->strength,
            $this->dosage_form,
        ])));
        $company = $this->companyLabel();

        return $company === '' ? $title : $title.' — '.$company;
    }
}
