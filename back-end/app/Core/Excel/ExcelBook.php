<?php

declare(strict_types=1);

namespace App\Core\Excel;

final class ExcelBook
{
    /**
     * @param  list<array{key: string, header: string, required?: bool}>  $columns
     * @param  \Closure(): iterable<int, array<string, scalar|null>>  $export
     * @param  \Closure(array<string, string>): string  $import
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $viewPermission,
        public string $managePermission,
        public array $columns,
        public \Closure $export,
        public \Closure $import,
        public bool $importable = true,
    ) {}
}
