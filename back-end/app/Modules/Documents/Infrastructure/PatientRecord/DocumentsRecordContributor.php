<?php

declare(strict_types=1);

namespace App\Modules\Documents\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Documents\Application\Queries\ResolveDocumentExamNames;
use App\Modules\Documents\Infrastructure\Persistence\Models\FileLinkModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The patient file lists documents, but a CT is hundreds of DICOM slices.
 * Those slices stay stored on the study; the file shows them as one study.
 */
final class DocumentsRecordContributor implements PatientRecordContributor
{
    public function __construct(private readonly ResolveDocumentExamNames $examNames) {}

    public function section(): string
    {
        return 'documents';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $links = FileLinkModel::query()
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId));

        try {
            $groups = $this->dicomStudies($patientId, $encounterId);
        } catch (\Throwable $error) {
            report($error);
            $groups = collect();
        }
        $lastIds = $groups->pluck('last_link_id')->map(fn ($id) => (int) $id)->filter()->all();
        $lastLinks = $lastIds === []
            ? collect()
            : FileLinkModel::query()->with('file.uploader')->whereIn('id', $lastIds)->get()->keyBy(fn (FileLinkModel $link) => $link->getKey());

        $others = FileLinkModel::query()
            ->with('file.uploader')
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId))
            ->where(function ($query): void {
                $query->where('entity_type', '!=', 'radiology_study')
                    ->orWhereHas('file', function ($file): void {
                        $file->where(function ($inner): void {
                            $inner->whereNull('mime_type')->orWhere('mime_type', 'not like', '%dicom%');
                        })->where(function ($inner): void {
                            $inner->whereNull('extension')->orWhereNotIn('extension', ['dcm', 'dicom']);
                        });
                    });
            })
            ->latest('id')
            ->limit(30)
            ->get()
            ->filter(fn (FileLinkModel $link) => $link->file !== null)
            ->values();

        $labels = $this->examNames->map($others->concat($lastLinks->values()));
        $rows = [];

        foreach ($groups as $group) {
            $link = $lastLinks->get((int) $group->last_link_id);

            if (! $link instanceof FileLinkModel || $link->file === null) {
                continue;
            }

            $rows[] = [
                'sort' => strtotime((string) ($group->created_at ?? '')) ?: 0,
                'row' => $this->present($link, $labels, (int) $group->file_count, (int) $group->total_size, $patientId),
            ];
        }

        foreach ($others as $link) {
            $rows[] = [
                'sort' => $link->file?->created_at?->getTimestamp() ?? 0,
                'row' => $this->present($link, $labels, 1, (int) ($link->file?->size ?? 0), $patientId),
            ];
        }

        usort($rows, fn (array $left, array $right) => $right['sort'] <=> $left['sort']);

        return [
            'total' => (clone $links)->count(),
            'by_category' => $this->countsByCategory($patientId, $encounterId),
            'recent' => array_values(array_map(fn (array $item) => $item['row'], array_slice($rows, 0, 30))),
        ];
    }

    /**
     * Grouped rows are plain aggregates. Hydrating them as file-link models
     * asks MySQL for columns that are not in the GROUP BY and blanks the file.
     *
     * @return Collection<int, stdClass>
     */
    private function dicomStudies(int $patientId, ?int $encounterId): Collection
    {
        return DB::table('file_links')
            ->join('files', 'files.id', '=', 'file_links.file_id')
            ->where('file_links.patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('file_links.encounter_id', $encounterId))
            ->where('file_links.entity_type', 'radiology_study')
            ->whereNull('files.deleted_at')
            ->where(function ($query): void {
                $query->where('files.mime_type', 'like', '%dicom%')
                    ->orWhereIn('files.extension', ['dcm', 'dicom']);
            })
            ->groupBy('file_links.entity_id')
            ->selectRaw('file_links.entity_id as study_id, COUNT(*) as file_count, MAX(file_links.id) as last_link_id, SUM(files.size) as total_size, MAX(files.created_at) as created_at')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    private function countsByCategory(int $patientId, ?int $encounterId): array
    {
        $rows = DB::table('file_links')
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId))
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) ($row->category ?? '')] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<string, mixed>
     */
    private function present(FileLinkModel $link, array $labels, int $count, int $size, int $patientId): array
    {
        $bundle = $count > 1 && $link->entity_type === 'radiology_study';

        return [
            'uuid' => $link->file?->uuid,
            'original_name' => $bundle ? $count.' ملف DICOM' : $link->file?->original_name,
            'mime_type' => $bundle ? 'application/dicom' : $link->file?->mime_type,
            'category' => $link->category,
            'exam_name' => $this->examNames->labelFor($link, $labels),
            'by' => $link->file?->uploader?->displayName(),
            'size' => $size,
            'created_at' => $link->file?->created_at?->toIso8601String(),
            'file_count' => $count,
            'archive_path' => $bundle
                ? '/patients/'.$patientId.'/imaging-studies/'.$link->entity_id.'/archive'
                : null,
        ];
    }
}
