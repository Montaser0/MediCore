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
     * @param  list<string>|null  $only  Null loads every section. A list loads just those tabs.
     * @return array<string, array<string, mixed>>
     */
    public function summarise(int $patientId, ?int $encounterId = null, ?array $only = null): array
    {
        $sections = [];

        foreach ($this->contributors as $section => $contributor) {
            if ($only !== null && ! in_array($section, $only, true)) {
                continue;
            }

            $sections[$section] = $contributor->summarise($patientId, $encounterId);
        }

        return $sections;
    }
}
