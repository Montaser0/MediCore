<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisCodeModel
 */
final class DiagnosisCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'system' => $this->system,
            'code' => $this->code,
            'title' => $this->title,
            'category' => $this->category,
        ];
    }
}
