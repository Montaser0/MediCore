<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Operations\Domain\Enums\OperationRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OperationTeamModel extends BaseModel
{
    protected $table = 'operation_team';

    protected function casts(): array
    {
        return [
            'role' => OperationRole::class,
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(OperationModel::class, 'operation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
