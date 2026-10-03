<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

/**
 * The patient file has to show laboratory, radiology, pharmacy and billing
 * information without the Patients module querying those tables itself.
 *
 * Each module registers a contributor for its own section; adding a new
 * department therefore means adding a contributor, not editing the patient
 * file code.
 */
interface PatientRecordContributor
{
    /**
     * Key the section appears under in the API response, e.g. `laboratory`.
     */
    public function section(): string;

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array;
}
