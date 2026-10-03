<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\EncounterContext;
use App\Modules\Nursing\Domain\Enums\AdministrationStatus;
use App\Modules\Nursing\Infrastructure\Persistence\Models\MedicationAdministrationModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionItemModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns a prescription line into the individual doses a nurse will tick off.
 * Producing the whole schedule up front is what makes an overdue dose
 * visible: a dose that was never created can never be missed.
 */
final class ScheduleMedicationAdministrationAction
{
    public function __construct(private readonly EncounterContext $encounters) {}

    /**
     * @param  array<int, string>  $times  clock times such as ['08:00', '20:00']
     * @return array<int, MedicationAdministrationModel>
     */
    public function execute(PrescriptionItemModel $item, array $times, int $days, ?Carbon $startingFrom = null): array
    {
        $prescription = $item->prescription;

        if ($prescription === null) {
            throw BusinessRuleException::make('بند الدواء غير مرتبط بوصفة.');
        }

        if ($times === [] || $days < 1) {
            throw BusinessRuleException::make('جدول الدواء يحتاج وقتاً ويوماً واحداً على الأقل.');
        }

        $encounter = $this->encounters->requireOpen((int) $prescription->encounter_id);
        $start = ($startingFrom ?? now())->startOfDay();

        return DB::transaction(function () use ($item, $times, $days, $start, $encounter): array {
            $created = [];

            for ($day = 0; $day < $days; $day++) {
                foreach ($times as $time) {
                    $scheduledAt = $start->copy()->addDays($day)->setTimeFromTimeString($time);

                    // Doses already in the past on the first day are skipped
                    // rather than created overdue on arrival.
                    if ($scheduledAt->isPast()) {
                        continue;
                    }

                    $created[] = MedicationAdministrationModel::query()->create([
                        'hospital_id' => $encounter->hospitalId,
                        'patient_id' => $encounter->patientId,
                        'encounter_id' => $encounter->id,
                        'prescription_item_id' => $item->getKey(),
                        'medication_id' => $item->medication_id,
                        'scheduled_at' => $scheduledAt,
                        'dose' => $item->dose,
                        'route' => $item->route,
                        'status' => AdministrationStatus::Due->value,
                    ]);
                }
            }

            return $created;
        });
    }
}
