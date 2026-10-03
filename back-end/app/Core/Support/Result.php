<?php

declare(strict_types=1);

namespace App\Core\Support;

use RuntimeException;

/**
 * Lightweight success/failure wrapper for services whose callers should
 * branch on the outcome instead of catching exceptions.
 *
 * @template TValue
 */
final class Result
{
    /**
     * @param  TValue|null  $value
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        private readonly bool $successful,
        private readonly mixed $value = null,
        private readonly ?string $error = null,
        private readonly array $context = [],
    ) {}

    /**
     * @template T
     *
     * @param  T  $value
     * @return self<T>
     */
    public static function success(mixed $value = null): self
    {
        return new self(true, $value);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return self<null>
     */
    public static function failure(string $error, array $context = []): self
    {
        return new self(false, null, $error, $context);
    }

    public function successful(): bool
    {
        return $this->successful;
    }

    public function failed(): bool
    {
        return ! $this->successful;
    }

    /**
     * @return TValue
     */
    public function value(): mixed
    {
        if ($this->failed()) {
            throw new RuntimeException('Cannot read the value of a failed result: '.$this->error);
        }

        return $this->value;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
