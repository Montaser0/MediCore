<?php

declare(strict_types=1);

namespace App\Modules\Patients\Infrastructure\Persistence\Repositories;

use App\Core\Persistence\BaseRepository;
use App\Modules\Patients\Domain\Exceptions\PatientNotFoundException;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;

/**
 * @extends BaseRepository<PatientModel>
 */
final class PatientRepository extends BaseRepository
{
    protected function model(): string
    {
        return PatientModel::class;
    }

    public function findByMrn(int $hospitalId, string $mrn): ?PatientModel
    {
        return $this->query()
            ->where('hospital_id', $hospitalId)
            ->where('mrn', $mrn)
            ->first();
    }

    public function requireByMrn(int $hospitalId, string $mrn): PatientModel
    {
        return $this->findByMrn($hospitalId, $mrn)
            ?? throw PatientNotFoundException::byMrn($mrn);
    }

    /**
     * Header data every clinical screen loads: identity plus the allergy and
     * chronic condition warnings.
     */
    public function withHeader(int $patientId): PatientModel
    {
        $patient = $this->query()
            ->with([
                'allergies' => fn ($query) => $query->active(),
                'conditions' => fn ($query) => $query->active(),
                'contacts' => fn ($query) => $query->where('is_emergency_contact', true),
            ])
            ->find($patientId);

        return $patient ?? throw PatientNotFoundException::byId($patientId);
    }
}
