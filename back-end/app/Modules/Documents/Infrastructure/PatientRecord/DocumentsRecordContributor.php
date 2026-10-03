<?php

declare(strict_types=1);

namespace App\Modules\Documents\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Documents\Application\Queries\ResolveDocumentExamNames;
use App\Modules\Documents\Infrastructure\Persistence\Models\FileLinkModel;

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

        $recent = (clone $links)
            ->with('file.uploader')
            ->latest('id')
            ->limit(30)
            ->get()
            ->filter(fn (FileLinkModel $link) => $link->file !== null)
            ->values();
        $labels = $this->examNames->map($recent);

        return [
            'total' => (clone $links)->count(),
            'by_category' => (clone $links)
                ->selectRaw('category, COUNT(*) as total')
                ->groupBy('category')
                ->pluck('total', 'category')
                ->all(),
            'recent' => $recent->map(fn (FileLinkModel $link) => [
                'uuid' => $link->file?->uuid,
                'original_name' => $link->file?->original_name,
                'mime_type' => $link->file?->mime_type,
                'category' => $link->category,
                'exam_name' => $this->examNames->labelFor($link, $labels),
                'by' => $link->file?->uploader?->displayName(),
                'size' => (int) ($link->file?->size ?? 0),
                'created_at' => $link->file?->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
