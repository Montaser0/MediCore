<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Domain\Events;

use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyStudyModel;
use Illuminate\Foundation\Events\Dispatchable;

final class ImagesUploaded
{
    use Dispatchable;

    public function __construct(
        public readonly RadiologyStudyModel $study,
        public readonly int $uploadedCount,
    ) {}
}
