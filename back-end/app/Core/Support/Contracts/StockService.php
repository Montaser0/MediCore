<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

/**
 * Implemented by the Inventory module. The pharmacy dispenses through this
 * contract, so stock rules (availability, expiry, batch selection and the
 * movement ledger) live in one place and cannot be bypassed.
 */
interface StockService
{
    public function availableQuantity(int $inventoryItemId, ?int $warehouseId = null): float;

    /**
     * Batches that may be issued, nearest expiry first, expired ones excluded.
     *
     * @return array<int, StockBatch>
     */
    public function issuableBatches(int $inventoryItemId, int $warehouseId): array;

    /**
     * Takes stock out of a warehouse, choosing batches by expiry when none is
     * named, and writes the movement ledger entries.
     *
     * @return array<int, StockBatch> the batches actually drawn from
     *
     * @throws \App\Modules\Inventory\Domain\Exceptions\InsufficientStockException
     * @throws \App\Modules\Inventory\Domain\Exceptions\ExpiredBatchException
     */
    public function issue(
        int $inventoryItemId,
        int $warehouseId,
        float $quantity,
        string $movementType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $batchId = null,
        ?string $notes = null,
    ): array;

    /**
     * Puts stock back, used for returns and cancelled dispenses.
     */
    public function receiveBack(
        int $inventoryItemId,
        int $warehouseId,
        float $quantity,
        ?int $batchId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
    ): void;
}
