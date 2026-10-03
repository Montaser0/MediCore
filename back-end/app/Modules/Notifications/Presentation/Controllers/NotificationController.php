<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationModel;
use App\Modules\Notifications\Presentation\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $notifications = NotificationModel::query()
            ->where('user_id', $request->user()?->getKey())
            ->when($request->boolean('unread_only'), fn ($query) => $query->unread())
            ->when($request->filled('type'), fn ($query) => $query->where('type', (string) $request->string('type')))
            ->orderByDesc('created_at')
            ->paginate($this->perPage(20));

        return ApiResponse::paginated(NotificationResource::collection($notifications), $notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::data([
            'unread' => NotificationModel::query()
                ->where('user_id', $request->user()?->getKey())
                ->unread()
                ->count(),
        ]);
    }

    public function markAsRead(Request $request, NotificationModel $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()?->getKey(), 403);

        $notification->forceFill(['read_at' => now()])->save();

        return ApiResponse::resource(new NotificationResource($notification));
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = NotificationModel::query()
            ->where('user_id', $request->user()?->getKey())
            ->unread()
            ->update(['read_at' => now()]);

        return ApiResponse::data(['marked_as_read' => $updated]);
    }
}
