<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Domain\Events;

use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabSampleModel;
use Illuminate\Foundation\Events\Dispatchable;

final class SampleCollected
{
    use Dispatchable;

    public function __construct(public readonly LabSampleModel $sample) {}
}
