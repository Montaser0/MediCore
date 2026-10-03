<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\AuditRecorder;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Nursing\Domain\Enums\AdministrationStatus;
use App\Modules\Nursing\Infrastructure\Persistence\Models\MedicationAdministrationModel;
use Illuminate\Support\Facades\DB;

/**
 * Signs off a dose. Every outcome — given, refused, held or omitted — is
 * recorded against the nurse who saw it, and the row is never revisited.
 */
final class RecordMedicationAdministrationAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(MedicationAdministrationModel $administration, array $data): MedicationAdministrationModel
    {
        $target = AdministrationStatus::from((string) $data['status']);
        $current = $administration->status;

        if (! $current->canTransitionTo($target)) {
            throw BusinessRuleException::make(
                'هذه الجرعة مسجّلة مسبقاً ولا يمكن تغييرها.',
                ['administration_id' => $administration->getKey()],
            );
        }

        if ($target->requiresReason() && blank($data['reason'] ?? null)) {
            throw BusinessRuleException::make('تسجيل الجرعة بهذه الحالة يحتاج سبباً.');
        }

        return DB::transaction(function () use ($administration, $target, $data): MedicationAdministrationModel {
            $administration->forceFill([
                'status' => $target->value,
                'administered_at' => $target === AdministrationStatus::Given ? ($data['administered_at'] ?? now()) : null,
                'administered_by' => $this->currentUser->id(),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $administration->loadMissing('medication');
            $medication = $administration->medication?->displayName() ?? 'Medication';

            $this->timeline->record(
                patientId: (int) $administration->patient_id,
                encounterId: (int) $administration->encounter_id,
                eventType: 'nursing.medication_'.$target->value,
                title: $target->label().': '.$medication,
                description: $administration->reason,
                payload: [
                    'dose' => $administration->dose,
                    'route' => $administration->route,
                    'scheduled_at' => $administration->scheduled_at?->toIso8601String(),
                ],
            );

            // Giving a drug is one of the sensitive operations the PRD asks
            // to be traceable to a person and a moment.
            $this->audit->record(
                action: 'nursing.medication_administered',
                entityType: 'medication_administration',
                entityId: (int) $administration->getKey(),
                newValues: [
                    'status' => $target->value,
                    'medication' => $medication,
                    'dose' => $administration->dose,
                ],
            );

            return $administration;
        });
    }
}
