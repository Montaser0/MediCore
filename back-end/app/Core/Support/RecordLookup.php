<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Support\Numbering\DailyNumber;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reception lookup: file number, visit number, phone, national id, or a name
 * that starts with what was typed. Leading wildcards are avoided so the
 * indexes on those columns can be used.
 */
final class RecordLookup
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function patients(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $term) === 1) {
            return $query->where('date_of_birth', $term);
        }

        $serial = DailyNumber::serial($term);
        $digits = PhoneNumber::digits($term);
        $hasLetter = preg_match('/\p{L}/u', $term) === 1;
        $structuredNumber = self::isStructuredNumber($term);

        return $query->where(function (Builder $inner) use ($term, $serial, $digits, $hasLetter, $structuredNumber): void {
            if ($structuredNumber) {
                $inner->where('mrn', $term)
                    ->orWhereHas('encounters', fn (Builder $encounters) => $encounters->where('encounter_number', $term));

                if ($serial !== null) {
                    $inner->orWhere('mrn_serial', $serial)
                        ->orWhereHas('encounters', fn (Builder $encounters) => $encounters->where('encounter_serial', $serial));
                }

                return;
            }

            if (! $hasLetter && strlen($digits) >= 7) {
                self::wherePhoneOrNationalId($inner, $term, $digits);

                return;
            }

            if (! $hasLetter && $serial !== null && preg_match('/^\d{1,6}$/', $term) === 1) {
                $inner->where('mrn_serial', $serial)
                    ->orWhere('mrn', $term)
                    ->orWhereHas('encounters', fn (Builder $encounters) => $encounters->where('encounter_serial', $serial));

                return;
            }

            self::whereName($inner, $term);
            $prefix = self::prefix($term);
            $inner->orWhere('mrn', 'like', $prefix)
                ->orWhereHas(
                    'encounters',
                    fn (Builder $encounters) => $encounters->where('encounter_number', 'like', $prefix),
                );
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function encounters(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $serial = DailyNumber::serial($term);
            $hasLetter = preg_match('/\p{L}/u', $term) === 1;
            $shortSerial = preg_match('/^\d{1,6}$/', $term) === 1;

            if (! $hasLetter && $serial !== null && ($shortSerial || self::isStructuredNumber($term))) {
                $inner->where('encounter_serial', $serial)
                    ->orWhere('encounter_number', $term)
                    ->orWhereHas('patient', fn (Builder $patient) => $patient->where('mrn_serial', $serial));

                return;
            }

            $inner->where('encounter_number', 'like', self::prefix($term))
                ->orWhereHas('patient', fn (Builder $patient) => $patient->search($term));
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function wherePhoneOrNationalId(Builder $query, string $term, string $digits): void
    {
        $query->where('national_id', $digits)->orWhere('national_id', $term);

        $tail = PhoneNumber::tail($term);
        if ($tail !== null) {
            $query->orWhere('phone_tail', $tail)
                ->orWhere('phone_secondary_tail', $tail);
        }

        $normalized = PhoneNumber::normalize($term);
        if ($normalized !== null) {
            $query->orWhere('phone_normalized', $normalized)
                ->orWhere('phone_secondary_normalized', $normalized);
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function whereName(Builder $query, string $term): void
    {
        $tokens = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $query->where(function (Builder $names) use ($tokens, $term): void {
            $names->where(function (Builder $parts) use ($tokens): void {
                foreach ($tokens as $token) {
                    $prefix = self::prefix($token);
                    $parts->where(function (Builder $part) use ($prefix): void {
                        $part->where('first_name', 'like', $prefix)
                            ->orWhere('father_name', 'like', $prefix)
                            ->orWhere('grandfather_name', 'like', $prefix)
                            ->orWhere('last_name', 'like', $prefix);
                    });
                }
            })->orWhere('name_en', 'like', self::prefix($term));

            foreach ($tokens as $token) {
                $names->orWhere('name_en', 'like', self::prefix($token));
            }
        });
    }

    private static function isStructuredNumber(string $term): bool
    {
        return preg_match('/^(?:\d{4}-\d{2}-\d{2}-\d+|(?:MRN|ENC|APT)-\d{4,8}-\d+)$/i', $term) === 1;
    }

    private static function prefix(string $value): string
    {
        return addcslashes($value, '\\%_').'%';
    }
}
