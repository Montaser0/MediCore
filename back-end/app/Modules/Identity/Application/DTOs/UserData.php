<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

final readonly class UserData
{
    /**
     * @param  array<int, string>  $roles
     * @param  array<int, int>  $departmentIds
     */
    public function __construct(
        public string $username,
        public ?string $email = null,
        public ?string $password = null,
        public ?int $staffId = null,
        public bool $isActive = true,
        public array $roles = [],
        public array $departmentIds = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            username: (string) $data['username'],
            email: $data['email'] ?? null,
            password: $data['password'] ?? null,
            staffId: isset($data['staff_id']) ? (int) $data['staff_id'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
            roles: $data['roles'] ?? [],
            departmentIds: $data['department_ids'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return array_filter([
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'staff_id' => $this->staffId,
            'is_active' => $this->isActive,
        ], static fn (mixed $value) => $value !== null);
    }
}
