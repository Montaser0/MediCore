<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Clinical\Domain\Enums\NoteType;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClinicalNoteModel extends BaseModel
{
    use Auditable;

    protected $table = 'clinical_notes';

    protected function casts(): array
    {
        return [
            'note_type' => NoteType::class,
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'author_id');
    }
}
