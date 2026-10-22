<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Application\Actions;

use App\Core\Storage\FileReference;

final readonly class ImagingUploadResult
{
    /**
     * @param  array<int, FileReference>  $files
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public array $files,
        public array $warnings,
        public int $skipped,
    ) {}
}
