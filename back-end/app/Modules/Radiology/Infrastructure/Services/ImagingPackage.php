<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Infrastructure\Services;

/**
 * Medical files taken out of an upload. A ZIP or a study folder is expanded
 * here; the workspace is temporary and must be removed after the bytes are stored.
 */
final class ImagingPackage
{
    /**
     * @param  array<int, AcceptedImage>  $files
     */
    public function __construct(
        public readonly array $files,
        public readonly int $skipped,
        private ?string $workspace,
    ) {}

    public function cleanup(): void
    {
        $directory = $this->workspace;
        $this->workspace = null;

        if ($directory === null) {
            return;
        }

        self::deleteTree($directory);
    }

    private static function deleteTree(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                self::deleteTree($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
