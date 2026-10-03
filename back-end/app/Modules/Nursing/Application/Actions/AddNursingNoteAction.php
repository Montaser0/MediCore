<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Nursing\Domain\Enums\NursingNoteType;
use App\Modules\Nursing\Domain\Enums\NursingShift;
use App\Modules\Nursing\Infrastructure\Persistence\Models\NursingNoteModel;

/**
 * Nursing notes are append-only: a correction is a new note, never an edit.
 */
final class AddNursingNoteAction
{
    public function __construct(
        private readonly EncounterContext $encounters,
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $encounterId, array $data): NursingNoteModel
    {
        $encounter = $this->encounters->requireOpen($encounterId);

        $type = NursingNoteType::from((string) ($data['note_type'] ?? NursingNoteType::Progress->value));

        $note = NursingNoteModel::query()->create([
            'hospital_id' => $encounter->hospitalId,
            'patient_id' => $encounter->patientId,
            'encounter_id' => $encounter->id,
            'admission_id' => $data['admission_id'] ?? null,
            'note_type' => $type->value,
            'shift' => $data['shift'] ?? NursingShift::at()->value,
            'content' => (string) $data['content'],
            'created_by' => $this->currentUser->id(),
        ]);

        $this->timeline->record(
            patientId: $encounter->patientId,
            encounterId: $encounter->id,
            eventType: 'nursing.note',
            title: $type->label(),
            description: str($note->content)->limit(160)->toString(),
        );

        return $note->load('author');
    }
}
