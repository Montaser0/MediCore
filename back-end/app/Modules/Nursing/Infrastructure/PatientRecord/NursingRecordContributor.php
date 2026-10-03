<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Nursing\Infrastructure\Persistence\Models\CarePlanModel;
use App\Modules\Nursing\Infrastructure\Persistence\Models\MedicationAdministrationModel;
use App\Modules\Nursing\Infrastructure\Persistence\Models\NursingNoteModel;

final class NursingRecordContributor implements PatientRecordContributor
{
    public function section(): string
    {
        return 'nursing';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $notes = NursingNoteModel::query()
            ->with('author')
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId))
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $plans = CarePlanModel::query()
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $doses = MedicationAdministrationModel::query()
            ->with('medication')
            ->where('patient_id', $patientId)
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId))
            ->orderByDesc('scheduled_at')
            ->limit(50)
            ->get();

        return [
            'notes' => $notes->map(fn (NursingNoteModel $note) => [
                'id' => $note->getKey(),
                'note_type' => $note->note_type->value,
                'shift' => $note->shift?->value,
                'content' => $note->content,
                'author' => $note->author?->displayName(),
                'by' => $note->author?->displayName(),
                'created_at' => $note->created_at?->toIso8601String(),
            ])->all(),
            'care_plans' => $plans->map(fn (CarePlanModel $plan) => [
                'id' => $plan->getKey(),
                'title' => $plan->title,
                'problem' => $plan->problem,
                'goal' => $plan->goal,
                'status' => $plan->status->value,
            ])->all(),
            'medication_administrations' => $doses->map(fn (MedicationAdministrationModel $dose) => [
                'id' => $dose->getKey(),
                'medication' => $dose->medication?->displayName(),
                'dose' => $dose->dose,
                'status' => $dose->status->value,
                'scheduled_at' => $dose->scheduled_at?->toIso8601String(),
                'administered_at' => $dose->administered_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
