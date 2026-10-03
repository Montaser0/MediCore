<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

/**
 * Lets a module open and close a visit without depending on the Encounters
 * module's action classes. Used when checking in an appointment and when
 * admitting or discharging a patient.
 */
interface EncounterFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function openFor(int $patientId, array $attributes = []): EncounterSnapshot;

    /**
     * Closes the visit. Already-closed visits are left alone so that callers
     * do not have to check first.
     */
    public function close(int $encounterId, ?string $note = null): void;
}
