<?php

declare(strict_types=1);

namespace App\Core\Support\Contracts;

/**
 * What a module learns about a batch it drew from, without reaching into the
 * inventory tables.
 */
final readonly class StockBatch
{
    public function __construct(
        public int $id,
        public string $batchNumber,
        public ?string $expiryDate,
        public float $quantity,
        public float $unitCost,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'batch_number' => $this->batchNumber,
            'expiry_date' => $this->expiryDate,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unitCost,
        ];
    }
}
