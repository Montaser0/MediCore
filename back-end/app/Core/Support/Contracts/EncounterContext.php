<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

/**
 * Implemented by the Encounters module. Any module that needs to hang data off
 * a visit resolves it through this contract, which also enforces that work is
 * only recorded against a visit that is still open.
 */
interface EncounterContext
{
    public function snapshot(int $encounterId): EncounterSnapshot;

    /**
     * @throws \App\Core\Exceptions\BusinessRuleException when the encounter is
     *   completed or cancelled.
     */
    public function requireOpen(int $encounterId): EncounterSnapshot;
}
