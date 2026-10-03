<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Support\Contracts\PatientRecordContributor;

/**
 * Collects the per-module contributors that make up the patient medical
 * record. Modules register themselves from their service provider during boot.
 */
final class PatientRecordRegistry
{
    /** @var array<string, PatientRecordContributor> */
    private array $contributors = [];

    public function register(PatientRecordContributor $contributor): void
    {
        $this->contributors[$contributor->section()] = $contributor;
    }

    /**
     * @return array<string, PatientRecordContributor>
     */
    public function all(): array
    {
        return $this->contributors;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $sections = [];

        foreach ($this->contributors as $section => $contributor) {
            $sections[$section] = $contributor->summarise($patientId, $encounterId);
        }

        return $sections;
    }
}
