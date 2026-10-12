<?php

declare(strict_types=1);

namespace App\Modules\Patients\Application\Queries;

use App\Core\Authorization\RecordVisibility;
use App\Core\Support\PatientRecordRegistry;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Patients\Infrastructure\Persistence\Repositories\PatientRepository;

/**
 * Builds the "Patient File" screen: the identity header plus one section per
 * module that has something to say about this patient.
 */
final class GetPatientFileQuery
{
    public function __construct(
        private readonly PatientRepository $patients,
        private readonly PatientRecordRegistry $registry,
    ) {}

    /**
     * @param  list<string>|null  $sections
     * @return array{patient: PatientModel, sections: array<string, array<string, mixed>>}
     */
    public function execute(int $patientId, ?int $encounterId = null, ?array $sections = null): array
    {
        $loaded = $this->registry->summarise($patientId, $encounterId, $sections);

        foreach (array_keys($loaded) as $section) {
            if (! RecordVisibility::sectionVisible($section)) {
                unset($loaded[$section]);
            }
        }

        return [
            'patient' => $this->patients->withHeader($patientId),
            'sections' => $loaded,
        ];
    }
}
