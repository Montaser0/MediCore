<?php

declare(strict_types=1);

namespace App\Modules\Patients\Application\Queries;

use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;

/**
 * A returning patient keeps the same medical file. A later visit, lab or
 * imaging study is attached to that file, never to a newly issued MRN.
 */
final class FindExistingPatientFile
{
    public function execute(
        int $hospitalId,
        ?string $nationalId,
        string $firstName,
        string $lastName,
        ?string $fatherName,
        ?string $phone,
        ?string $dateOfBirth,
        ?int $exceptId = null,
    ): ?PatientModel {
        $base = PatientModel::query()
            ->where('hospital_id', $hospitalId)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId));

        $nationalId = $this->blankToNull($nationalId);
        if ($nationalId !== null) {
            $match = (clone $base)->where('national_id', $nationalId)->first();
            if ($match !== null) {
                return $match;
            }
        }

        $phone = $this->blankToNull($phone);
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        if ($phone !== null && $firstName !== '' && $lastName !== '') {
            $match = (clone $base)
                ->where('phone', $phone)
                ->where('first_name', $firstName)
                ->where('last_name', $lastName)
                ->first();
            if ($match !== null) {
                return $match;
            }
        }

        $dateOfBirth = $this->blankToNull($dateOfBirth);
        $fatherName = $this->blankToNull($fatherName);
        if ($dateOfBirth !== null && $firstName !== '' && $lastName !== '' && $fatherName !== null) {
            return (clone $base)
                ->where('first_name', $firstName)
                ->where('father_name', $fatherName)
                ->where('last_name', $lastName)
                ->whereDate('date_of_birth', $dateOfBirth)
                ->first();
        }

        return null;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
