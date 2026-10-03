<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Infrastructure\Services;

use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\EncounterFactory;
use App\Core\Support\Contracts\EncounterSnapshot;
use App\Modules\Encounters\Application\Actions\ChangeEncounterStatusAction;
use App\Modules\Encounters\Application\Actions\CreateEncounterAction;
use App\Modules\Encounters\Domain\Enums\EncounterStatus;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;

final class EncounterFactoryService implements EncounterFactory
{
    public function __construct(
        private readonly CreateEncounterAction $createEncounter,
        private readonly ChangeEncounterStatusAction $changeStatus,
        private readonly EncounterContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function openFor(int $patientId, array $attributes = []): EncounterSnapshot
    {
        $patient = PatientModel::query()->findOrFail($patientId);

        $encounter = $this->createEncounter->execute($patient, $attributes);

        return $this->context->snapshot($encounter->getKey());
    }

    public function close(int $encounterId, ?string $note = null): void
    {
        /** @var EncounterModel $encounter */
        $encounter = EncounterModel::query()->findOrFail($encounterId);

        if ($encounter->status->isTerminal()) {
            return;
        }

        // A visit that never got as far as being seen still has to pass
        // through in_progress to satisfy the status engine.
        if (! $encounter->status->canTransitionTo(EncounterStatus::Completed)) {
            $this->changeStatus->execute($encounter, EncounterStatus::InProgress, $note);
        }

        $this->changeStatus->execute($encounter, EncounterStatus::Completed, $note);
    }
}
