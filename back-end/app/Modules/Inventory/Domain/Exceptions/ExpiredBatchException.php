<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use App\Core\Exceptions\DomainException;

/**
 * An expired batch must never leave the store, however much of it is on the
 * shelf (PRD: expiry check before dispensing).
 */
final class ExpiredBatchException extends DomainException
{
    protected int $status = 409;

    protected string $errorCode = 'inventory.batch_expired';

    public static function for(string $batchNumber, ?string $expiryDate = null): self
    {
        return new self(
            "الدفعة {$batchNumber} منتهية الصلاحية ولا يمكن صرفها.",
            ['batch_number' => $batchNumber, 'expiry_date' => $expiryDate],
        );
    }
}
