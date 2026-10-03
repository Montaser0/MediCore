<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Exceptions;

use App\Core\Exceptions\DomainException;

/**
 * Raised when someone tries to enter a result before a usable sample exists.
 * The laboratory workflow is deliberately strict about this.
 */
final class SampleNotReadyException extends DomainException
{
    protected int $status = 409;

    protected string $errorCode = 'lab.sample_not_ready';

    public static function for(int $labOrderItemId): self
    {
        return new self(
            'لا توجد عينة مسحوبة لهذا التحليل بعد.',
            ['lab_order_item_id' => $labOrderItemId],
        );
    }
}
