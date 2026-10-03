<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

/**
 * Implemented by the Orders module. A department that needs to raise work for
 * another department (a prescription that must reach the pharmacy, say) goes
 * through this rather than writing to `orders` itself.
 */
interface OrderCoordinator
{
    /**
     * @param  array<string, mixed>  $data
     * @return int the new order id
     */
    public function place(int $encounterId, string $type, array $data): int;

    public function markInProgress(int $orderId): void;

    public function markCompleted(int $orderId): void;

    public function markItemCompleted(int $orderItemId): void;
}
