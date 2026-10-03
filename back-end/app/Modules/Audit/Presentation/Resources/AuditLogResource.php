<?php

declare(strict_types=1);

namespace App\Modules\Audit\Presentation\Resources;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLogModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLogModel
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_label' => $this->entity_type === null ? null : class_basename($this->entity_type),
            'entity_id' => $this->entity_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'username' => $this->user->username,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
