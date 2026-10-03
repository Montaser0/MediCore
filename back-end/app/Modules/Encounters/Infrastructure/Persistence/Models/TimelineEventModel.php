<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $event_type
 * @property \Illuminate\Support\Carbon $occurred_at
 */
final class TimelineEventModel extends BaseModel
{
    public $timestamps = false;

    protected $table = 'timeline_events';

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'actor_id');
    }

    /**
     * @return BelongsTo<DepartmentModel, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }
}
