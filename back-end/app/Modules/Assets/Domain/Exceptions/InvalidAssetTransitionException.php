<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Exceptions;

use App\Core\Exceptions\DomainException;
use App\Modules\Assets\Domain\Enums\AssetStatus;

final class InvalidAssetTransitionException extends DomainException
{
    protected string $errorCode = 'assets.invalid_transition';

    public static function forStatus(AssetStatus $status): self
    {
        return new self("لا يمكن تنفيذ هذا الإجراء والجهاز {$status->label()}.");
    }

    public static function because(string $message): self
    {
        return new self($message);
    }
}
