<?php

declare(strict_types=1);

namespace App\Modules\Documents\Application\Queries;

use App\Modules\Documents\Infrastructure\Persistence\Models\FileLinkModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyStudyModel;
use Illuminate\Support\Collection;

/**
 * Turns a stored file link into the examination it belongs to,
 * for example "تحليل تعداد دم كامل" or "صورة إيكو قلب".
 */
final class ResolveDocumentExamNames
{
    /**
     * @param  Collection<int, FileLinkModel>  $links
     * @return array<string, string>
     */
    public function map(Collection $links): array
    {
        $grouped = $links->groupBy('entity_type');
        $names = [];

        $labIds = $this->ids($grouped, 'lab_order_item');
        if ($labIds !== []) {
            LabOrderItemModel::query()
                ->with('test')
                ->whereIn('id', $labIds)
                ->get()
                ->each(function (LabOrderItemModel $item) use (&$names): void {
                    $names['lab_order_item:'.$item->getKey()] = $this->phrase('تحليل', (string) ($item->test?->name ?? ''));
                });
        }

        $radiologyIds = $this->ids($grouped, 'radiology_order_item');
        if ($radiologyIds !== []) {
            RadiologyOrderItemModel::query()
                ->with('service')
                ->whereIn('id', $radiologyIds)
                ->get()
                ->each(function (RadiologyOrderItemModel $item) use (&$names): void {
                    $names['radiology_order_item:'.$item->getKey()] = $this->phrase('صورة', (string) ($item->service?->name ?? ''));
                });
        }

        $studyIds = $this->ids($grouped, 'radiology_study');
        if ($studyIds !== []) {
            RadiologyStudyModel::query()
                ->with('orderItem.service')
                ->whereIn('id', $studyIds)
                ->get()
                ->each(function (RadiologyStudyModel $study) use (&$names): void {
                    $names['radiology_study:'.$study->getKey()] = $this->phrase('صورة', (string) ($study->orderItem?->service?->name ?? ''));
                });
        }

        $prescriptionIds = $this->ids($grouped, 'prescription');
        if ($prescriptionIds !== []) {
            PrescriptionModel::query()
                ->with('items.medication')
                ->whereIn('id', $prescriptionIds)
                ->get()
                ->each(function (PrescriptionModel $prescription) use (&$names): void {
                    $medications = $prescription->items
                        ->map(fn ($item) => $item->medication?->name)
                        ->filter(fn ($name) => is_string($name) && trim($name) !== '')
                        ->unique()
                        ->implode('، ');
                    $names['prescription:'.$prescription->getKey()] = $this->phrase('دواء', $medications);
                });
        }

        return $names;
    }

    /**
     * @param  array<string, string>  $names
     */
    public function labelFor(FileLinkModel $link, array $names): string
    {
        return $names[$link->entity_type.':'.$link->entity_id]
            ?? $this->phrase(match ($link->entity_type) {
                'lab_order_item' => 'تحليل',
                'radiology_order_item', 'radiology_study' => 'صورة',
                'prescription' => 'دواء',
                default => '',
            }, '');
    }

    /**
     * @param  Collection<string, Collection<int, FileLinkModel>>  $grouped
     * @return array<int, int>
     */
    private function ids(Collection $grouped, string $entityType): array
    {
        return $grouped->get($entityType, collect())
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function phrase(string $kind, string $name): string
    {
        $name = trim($name);
        if ($kind === '') {
            return $name;
        }
        if ($name === '' || str_starts_with($name, $kind)) {
            return $name === '' ? $kind : $name;
        }

        return $kind.' '.$name;
    }
}
