<?php

declare(strict_types=1);

namespace App\Core\Backup;

/**
 * Decides when the daily USB copy is due, and which older files to drop.
 */
final class BackupSchedule
{
    public static function isDue(\DateTimeImmutable $now, string $time, ?string $lastOn): bool
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $matches) !== 1) {
            return false;
        }

        if ($lastOn === $now->format('Y-m-d')) {
            return false;
        }

        $due = $now->setTime((int) $matches[1], (int) $matches[2], 0);

        return $now >= $due;
    }

    /**
     * @param  list<string>  $names  newest first
     * @return list<string>
     */
    public static function expired(array $names, int $keep): array
    {
        return array_values(array_slice($names, max(1, $keep)));
    }
}
