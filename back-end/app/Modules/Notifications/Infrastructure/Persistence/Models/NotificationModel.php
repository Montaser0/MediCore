<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $read_at
 */
final class NotificationModel extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'notifications';

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    /**
     * @param  Builder<NotificationModel>  $query
     * @return Builder<NotificationModel>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
