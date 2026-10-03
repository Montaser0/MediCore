<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

/**
 * Feeds the patient timeline described in the PRD. Modules announce what
 * happened; the Encounters module decides how it is stored and displayed.
 */
interface TimelineRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        int $patientId,
        ?int $encounterId,
        string $eventType,
        string $title,
        ?string $description = null,
        ?int $departmentId = null,
        array $payload = [],
    ): void;
}
