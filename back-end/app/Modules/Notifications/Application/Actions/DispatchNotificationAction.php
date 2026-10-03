<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationModel;
use Illuminate\Support\Facades\DB;

final class DispatchNotificationAction implements NotificationDispatcher
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    /**
     * @param  array<int, int>|int  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function toUsers(
        array|int $userIds,
        string $type,
        string $title,
        ?string $message = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $payload = [],
    ): void {
        $ids = array_values(array_unique(array_filter(
            is_array($userIds) ? $userIds : [$userIds],
            static fn (?int $id) => $id !== null && $id > 0,
        )));

        if ($ids === []) {
            return;
        }

        $now = now();

        DB::table('notifications')->insert(array_map(static fn (int $userId) => [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => $now,
        ], $ids));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toPermissionHolders(
        string $permission,
        string $type,
        string $title,
        ?string $message = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $payload = [],
    ): void {
        $userIds = UserModel::query()
            ->active()
            ->permission($permission)
            ->when(
                $this->currentUser->hospitalId() !== null,
                fn ($query) => $query->whereHas(
                    'staff',
                    fn ($staff) => $staff->where('hospital_id', $this->currentUser->hospitalId())
                )
            )
            ->pluck('id')
            ->all();

        $this->toUsers($userIds, $type, $title, $message, $entityType, $entityId, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toDepartment(
        int $departmentId,
        string $type,
        string $title,
        ?string $message = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $payload = [],
    ): void {
        $userIds = UserModel::query()
            ->active()
            ->where(function ($query) use ($departmentId): void {
                $query->whereHas('staff', fn ($staff) => $staff->where('department_id', $departmentId))
                    ->orWhereHas('departments', fn ($departments) => $departments->where('departments.id', $departmentId));
            })
            ->pluck('id')
            ->all();

        $this->toUsers($userIds, $type, $title, $message, $entityType, $entityId, $payload);
    }

    public function markAsRead(NotificationModel $notification): NotificationModel
    {
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification;
    }
}
