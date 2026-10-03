<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Clinical\Infrastructure\Persistence\Models\VitalSignModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RecordVitalSignsAction
{
    public function __construct(
        private readonly EncounterContext $encounters,
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $encounterId, array $data): VitalSignModel
    {
        $encounter = $this->encounters->requireOpen($encounterId);

        return DB::transaction(function () use ($encounter, $data): VitalSignModel {
            $reading = VitalSignModel::query()->create([
                'encounter_id' => $encounter->id,
                'temperature' => $data['temperature'] ?? null,
                'systolic_bp' => $data['systolic_bp'] ?? null,
                'diastolic_bp' => $data['diastolic_bp'] ?? null,
                'heart_rate' => $data['heart_rate'] ?? null,
                'respiratory_rate' => $data['respiratory_rate'] ?? null,
                'oxygen_saturation' => $data['oxygen_saturation'] ?? null,
                'weight' => $data['weight'] ?? null,
                'height' => $data['height'] ?? null,
                'recorded_by' => $this->currentUser->id(),
                'recorded_at' => isset($data['recorded_at']) ? Carbon::parse((string) $data['recorded_at']) : now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $abnormal = $reading->abnormalities();

            $this->timeline->record(
                patientId: $encounter->patientId,
                encounterId: $encounter->id,
                eventType: 'clinical.vitals_recorded',
                title: 'سُجّلت العلامات الحيوية',
                description: $abnormal === []
                    ? 'كل القراءات ضمن المجال الطبيعي'
                    : count($abnormal).' قراءة خارج المجال الطبيعي',
                departmentId: $encounter->departmentId,
                payload: ['vital_sign_id' => $reading->getKey(), 'abnormal' => $abnormal],
            );

            return $reading;
        });
    }
}
