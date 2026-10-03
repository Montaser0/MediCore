<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Support\Contracts\OrderFulfiller;

/**
 * Holds the department fulfillers, keyed by order type. Populated from each
 * department's service provider during boot.
 */
final class OrderFulfillerRegistry
{
    /**
     * @var array<string, OrderFulfiller>
     */
    private array $fulfillers = [];

    public function register(OrderFulfiller $fulfiller): void
    {
        $this->fulfillers[$fulfiller->orderType()] = $fulfiller;
    }

    public function for(string $orderType): ?OrderFulfiller
    {
        return $this->fulfillers[$orderType] ?? null;
    }

    public function has(string $orderType): bool
    {
        return isset($this->fulfillers[$orderType]);
    }

    /**
     * @return array<int, string>
     */
    public function registeredTypes(): array
    {
        return array_keys($this->fulfillers);
    }
}
