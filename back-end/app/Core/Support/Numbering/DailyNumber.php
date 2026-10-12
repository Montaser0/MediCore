<?php

declare(strict_types=1);

namespace App\Core\Support\Numbering;

/**
 * Staff see a plain daily serial (1, 2, 3). The database keeps a unique
 * value of the form Y-m-d-N so the same serial can start again tomorrow.
 */
final class DailyNumber
{
    public static function present(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}-(\d+)$/', $value, $matches) === 1) {
            return (string) (int) $matches[1];
        }

        if (preg_match('/^(?:MRN|ENC|APT)-\d{4,8}-(\d+)$/i', $value, $matches) === 1) {
            return (string) (int) $matches[1];
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return (string) (int) $value;
        }

        return $value;
    }
}
