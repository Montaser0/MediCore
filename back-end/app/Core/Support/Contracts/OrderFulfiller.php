<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

/**
 * Implemented by every department that receives work through the order
 * engine. The Orders module knows nothing about laboratories or pharmacies:
 * it creates the order and hands it to whichever fulfiller claims the type.
 *
 * Adding a new department therefore means adding a fulfiller, not editing the
 * order engine.
 */
interface OrderFulfiller
{
    /**
     * Order type this fulfiller claims, e.g. `laboratory`.
     */
    public function orderType(): string;

    /**
     * Called inside the order-creation transaction. The department creates its
     * own rows (lab_orders, radiology_orders, prescriptions, ...) here.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function fulfil(int $orderId, array $items): void;

    /**
     * Called when a pending order is cancelled, so the department can void its
     * own rows.
     */
    public function cancel(int $orderId, ?string $reason = null): void;
}
