<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Operations\Domain\Enums\OperationNoteType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OperationNoteModel extends BaseModel
{
    use Auditable;

    protected $table = 'operation_notes';

    protected function casts(): array
    {
        return [
            'note_type' => OperationNoteType::class,
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(OperationModel::class, 'operation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }
}
