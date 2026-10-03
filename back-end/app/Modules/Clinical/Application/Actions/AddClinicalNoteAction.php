<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Clinical\Domain\Enums\NoteType;
use App\Modules\Clinical\Infrastructure\Persistence\Models\ClinicalNoteModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AddClinicalNoteAction
{
    public function __construct(
        private readonly EncounterContext $encounters,
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $encounterId, array $data): ClinicalNoteModel
    {
        $encounter = $this->encounters->requireOpen($encounterId);
        $type = NoteType::from((string) ($data['note_type'] ?? NoteType::General->value));

        return DB::transaction(function () use ($encounter, $data, $type): ClinicalNoteModel {
            $note = ClinicalNoteModel::query()->create([
                'encounter_id' => $encounter->id,
                'author_id' => $this->currentUser->id(),
                'note_type' => $type->value,
                'content' => (string) $data['content'],
            ]);

            $this->timeline->record(
                patientId: $encounter->patientId,
                encounterId: $encounter->id,
                eventType: 'clinical.note_added',
                title: $type->label(),
                description: Str::limit(strip_tags((string) $data['content']), 160),
                departmentId: $encounter->departmentId,
                payload: ['clinical_note_id' => $note->getKey(), 'note_type' => $type->value],
            );

            return $note->load('author');
        });
    }
}
