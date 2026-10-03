<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

final readonly class CredentialsData
{
    public function __construct(
        public string $username,
        public string $password,
        public string $deviceName = 'web',
        public ?string $ipAddress = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $ipAddress = null): self
    {
        return new self(
            username: (string) $data['username'],
            password: (string) $data['password'],
            deviceName: (string) ($data['device_name'] ?? 'web'),
            ipAddress: $ipAddress,
        );
    }
}
