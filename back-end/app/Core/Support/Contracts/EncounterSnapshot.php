<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

/**
 * The minimum an order, charge or result needs to know about the visit it
 * belongs to. Passing this around means the laboratory never loads the
 * Encounters module's Eloquent model.
 */
final readonly class EncounterSnapshot
{
    public function __construct(
        public int $id,
        public string $encounterNumber,
        public int $patientId,
        public int $hospitalId,
        public ?int $departmentId,
        public ?int $clinicId,
        public ?int $doctorId,
        public string $type,
        public string $status,
        public bool $isOpen,
    ) {}
}
