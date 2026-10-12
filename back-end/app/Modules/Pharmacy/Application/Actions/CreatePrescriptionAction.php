<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Core\Support\Numbering\NumberGenerator;
use App\Modules\Pharmacy\Domain\Enums\PrescriptionStatus;
use App\Modules\Pharmacy\Domain\Events\PrescriptionCreated;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionModel;
use Illuminate\Support\Facades\DB;

/**
 * The doctor writes a prescription against the visit. It is issued straight
 * away unless saved as a draft, and the pharmacy is told once it is issued.
 */
final class CreatePrescriptionAction
{
    public function __construct(
        private readonly EncounterContext $encounters,
        private readonly NumberGenerator $numbers,
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): PrescriptionModel
    {
        $encounter = $this->encounters->requireOpen((int) $data['encounter_id']);

        $doctorId = $data['doctor_id'] ?? $encounter->doctorId ?? $this->currentUser->doctorId();

        if ($doctorId === null) {
            throw BusinessRuleException::make('الوصفة يجب أن يكتبها طبيب.');
        }

        $items = $data['items'] ?? [];

        if ($items === []) {
            throw BusinessRuleException::make('الوصفة تحتاج دواءً واحداً على الأقل.');
        }

        $status = ($data['is_draft'] ?? false)
            ? PrescriptionStatus::Draft
            : PrescriptionStatus::Active;

        return DB::transaction(function () use ($encounter, $data, $doctorId, $items, $status): PrescriptionModel {
            $prescription = PrescriptionModel::query()->create([
                'hospital_id' => $encounter->hospitalId,
                'encounter_id' => $encounter->id,
                'prescription_number' => $this->numbers->prescriptionNumber($encounter->hospitalId),
                'doctor_id' => $doctorId,
                'entered_by' => $this->currentUser->id(),
                'order_id' => $data['order_id'] ?? null,
                'status' => $status->value,
                'notes' => $data['notes'] ?? null,
                'prescribed_at' => now(),
            ]);

            foreach ($items as $item) {
                $medication = MedicationModel::query()->findOrFail((int) $item['medication_id']);

                $prescription->items()->create([
                    'medication_id' => $medication->getKey(),
                    'dose' => $item['dose'] ?? null,
                    'frequency' => $item['frequency'] ?? null,
                    'duration' => $item['duration'] ?? null,
                    'route' => $item['route'] ?? $medication->route,
                    'quantity' => $item['quantity'] ?? null,
                    'instructions' => $item['instructions'] ?? null,
                ]);
            }

            $prescription->load(['items.medication', 'doctor.staff', 'enteredBy.staff', 'enteredBy.roles']);

            PrescriptionCreated::dispatch($prescription);

            $this->timeline->record(
                patientId: $encounter->patientId,
                encounterId: $encounter->id,
                eventType: 'pharmacy.prescription_created',
                title: 'وصفة طبية',
                description: $prescription->items
                    ->map(fn ($item) => $item->medication?->displayName())
                    ->filter()
                    ->implode(', '),
                departmentId: $encounter->departmentId,
                payload: ['prescription_id' => $prescription->getKey(), 'status' => $status->value],
            );

            if ($status === PrescriptionStatus::Active) {
                $this->notifications->toPermissionHolders(
                    permission: 'pharmacy.dispensing.create',
                    type: 'pharmacy.prescription_created',
                    title: 'وصفة جديدة للصرف',
                    message: 'عدد البنود '.$prescription->items->count(),
                    entityType: 'prescription',
                    entityId: $prescription->getKey(),
                );
            }

            return $prescription;
        });
    }
}
