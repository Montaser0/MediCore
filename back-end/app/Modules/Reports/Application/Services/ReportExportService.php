<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a report as CSV rather than building the whole file in memory,
 * because a year of payments is not something to hold in a PHP array.
 *
 * Excel and PDF renderers can be added behind the same call; the callers do
 * not need to know which one produced the bytes.
 */
final class ReportExportService
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>|null  $columns  defaults to the keys of the first row
     */
    public function csv(string $filename, array $rows, ?array $columns = null): StreamedResponse
    {
        $columns ??= $rows === [] ? [] : array_keys($rows[0]);

        return response()->streamDownload(function () use ($rows, $columns): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // Byte order mark so Excel opens Arabic column values correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            if ($columns !== []) {
                fputcsv($handle, $columns);
            }

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    static fn (string $column): string => self::stringify($row[$column] ?? null),
                    $columns,
                ));
            }

            fclose($handle);
        }, $this->safeFilename($filename), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '',
            default => (string) $value,
        };
    }

    private function safeFilename(string $filename): string
    {
        $base = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($filename, PATHINFO_FILENAME)) ?: 'report';

        return $base.'_'.now()->format('Ymd_His').'.csv';
    }
}
