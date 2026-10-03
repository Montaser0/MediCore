<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use App\Core\Exceptions\DomainException;

/**
 * Raised instead of letting stock go negative, which is what makes the
 * pharmacy refuse to dispense rather than silently overdraw.
 */
final class InsufficientStockException extends DomainException
{
    protected int $status = 409;

    protected string $errorCode = 'inventory.insufficient_stock';

    public static function for(string $itemName, float $requested, float $available): self
    {
        return new self(
            "المتوفر من {$itemName} هو {$available} فقط، والمطلوب {$requested}.",
            ['item' => $itemName, 'requested' => $requested, 'available' => $available],
        );
    }
}
