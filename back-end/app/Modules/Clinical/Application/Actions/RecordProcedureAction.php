<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Modules\Clinical\Domain\Events\ProcedurePerformed;
use App\Modules\Clinical\Infrastructure\Persistence\Models\ProcedureModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RecordProcedureAction
{
    public function __construct(
        private readonly EncounterContext $encounters,
        private readonly CurrentUser $currentUser,
        private readonly TimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $encounterId, array $data): ProcedureModel
    {
        $encounter = $this->encounters->requireOpen($encounterId);

        return DB::transaction(function () use ($encounter, $data): ProcedureModel {
            $name = trim((string) ($data['name'] ?? ''));
            $code = $data['code'] ?? null;

            if ($name === '' && isset($data['service_id'])) {
                $service = DB::table('services')->where('id', $data['service_id'])->first();
                $name = (string) ($service?->name ?? '');
                $code ??= $service?->code ?? null;
            }

            $procedure = ProcedureModel::query()->create([
                'encounter_id' => $encounter->id,
                'doctor_id' => $data['doctor_id'] ?? $encounter->doctorId ?? $this->currentUser->doctorId(),
                'service_id' => $data['service_id'] ?? null,
                'name' => $name,
                'code' => $code,
                'description' => $data['description'] ?? null,
                'outcome' => $data['outcome'] ?? null,
                'performed_at' => isset($data['performed_at']) ? Carbon::parse((string) $data['performed_at']) : now(),
                'recorded_by' => $this->currentUser->id(),
            ]);

            ProcedurePerformed::dispatch($procedure);

            $this->timeline->record(
                patientId: $encounter->patientId,
                encounterId: $encounter->id,
                eventType: 'clinical.procedure_recorded',
                title: 'إجراء: '.$procedure->name,
                description: $procedure->outcome,
                departmentId: $encounter->departmentId,
                payload: ['procedure_id' => $procedure->getKey()],
            );

            return $procedure->load(['doctor.staff', 'recordedBy.staff', 'recordedBy.roles']);
        });
    }
}
