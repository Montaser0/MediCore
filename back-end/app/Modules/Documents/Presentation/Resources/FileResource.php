<?php

declare(strict_types=1);

namespace App\Modules\Documents\Presentation\Resources;

use App\Modules\Documents\Infrastructure\Persistence\Models\FileModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FileModel
 */
final class FileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => (int) $this->size,
            'exam_name' => $this->exam_name,
            'download_path' => $this->downloadPath(),
            'preview_path' => $this->downloadPath().'?inline=1',
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader === null ? null : [
                'id' => $this->uploader->id,
                'username' => $this->uploader->username,
            ]),
            'links' => $this->whenLoaded('links', fn () => $this->links->map(fn ($link) => [
                'entity_type' => $link->entity_type,
                'entity_id' => $link->entity_id,
                'category' => $link->category,
                'patient_id' => $link->patient_id,
                'encounter_id' => $link->encounter_id,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
