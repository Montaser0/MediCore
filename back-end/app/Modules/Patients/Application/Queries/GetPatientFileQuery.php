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
     * @return array{patient: PatientModel, sections: array<string, array<string, mixed>>}
     */
    public function execute(int $patientId, ?int $encounterId = null): array
    {
        $sections = $this->registry->summarise($patientId, $encounterId);

        foreach (array_keys($sections) as $section) {
            if (! RecordVisibility::sectionVisible($section)) {
                unset($sections[$section]);
            }
        }

        return [
            'patient' => $this->patients->withHeader($patientId),
            'sections' => $sections,
        ];
    }
}
