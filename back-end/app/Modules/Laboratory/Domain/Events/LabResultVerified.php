<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Events;

use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabResultModel;
use Illuminate\Foundation\Events\Dispatchable;

final class LabResultVerified
{
    use Dispatchable;

    public function __construct(public readonly LabResultModel $result) {}
}
