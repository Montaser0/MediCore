<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Resources;

use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationModel
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = NotificationType::tryFrom($this->type);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'is_critical' => $type?->isCritical() ?? false,
            'title' => $this->title,
            'message' => $this->message,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'payload' => $this->payload,
            'read_at' => $this->read_at?->toIso8601String(),
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
