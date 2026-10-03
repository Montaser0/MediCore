<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $action
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 */
final class AuditLogModel extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
