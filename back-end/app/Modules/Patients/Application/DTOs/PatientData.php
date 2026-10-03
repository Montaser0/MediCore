<?php

declare(strict_types=1);

namespace App\Modules\Patients\Application\DTOs;

use App\Modules\Patients\Domain\Enums\Gender;
use App\Modules\Patients\Domain\Enums\PatientStatus;

final readonly class PatientData
{
    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @param  array<int, array<string, mixed>>  $allergies
     * @param  array<int, array<string, mixed>>  $conditions
     */
    public function __construct(
        public int $hospitalId,
        public string $firstName,
        public string $lastName,
        public Gender $gender,
        public ?string $fatherName = null,
        public ?string $grandfatherName = null,
        public ?string $motherName = null,
        public ?string $dateOfBirth = null,
        public ?string $nationalId = null,
        public ?string $phone = null,
        public ?string $phoneSecondary = null,
        public ?string $email = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $nationality = null,
        public ?string $bloodType = null,
        public ?string $maritalStatus = null,
        public ?string $occupation = null,
        public PatientStatus $status = PatientStatus::Active,
        public ?string $notes = null,
        public array $contacts = [],
        public array $allergies = [],
        public array $conditions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, int $hospitalId): self
    {
        return new self(
            hospitalId: $hospitalId,
            firstName: (string) $data['first_name'],
            lastName: (string) $data['last_name'],
            gender: Gender::from((string) $data['gender']),
            fatherName: $data['father_name'] ?? null,
            grandfatherName: $data['grandfather_name'] ?? null,
            motherName: $data['mother_name'] ?? null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            nationalId: $data['national_id'] ?? null,
            phone: $data['phone'] ?? null,
            phoneSecondary: $data['phone_secondary'] ?? null,
            email: $data['email'] ?? null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            nationality: $data['nationality'] ?? null,
            bloodType: $data['blood_type'] ?? null,
            maritalStatus: $data['marital_status'] ?? null,
            occupation: $data['occupation'] ?? null,
            status: PatientStatus::from((string) ($data['status'] ?? PatientStatus::Active->value)),
            notes: $data['notes'] ?? null,
            contacts: $data['contacts'] ?? [],
            allergies: $data['allergies'] ?? [],
            conditions: $data['conditions'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'hospital_id' => $this->hospitalId,
            'first_name' => $this->firstName,
            'father_name' => $this->fatherName,
            'grandfather_name' => $this->grandfatherName,
            'last_name' => $this->lastName,
            'mother_name' => $this->motherName,
            'gender' => $this->gender->value,
            'date_of_birth' => $this->dateOfBirth,
            'national_id' => $this->blankToNull($this->nationalId),
            'phone' => $this->phone,
            'phone_secondary' => $this->phoneSecondary,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'nationality' => $this->nationality,
            'blood_type' => $this->bloodType,
            'marital_status' => $this->maritalStatus,
            'occupation' => $this->occupation,
            'status' => $this->status->value,
            'notes' => $this->notes,
        ];
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
