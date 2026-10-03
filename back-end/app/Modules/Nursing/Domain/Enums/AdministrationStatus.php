<?php

declare(strict_types=1);

namespace App\Modules\Nursing\Domain\Enums;

/**
 * The medication administration record. A dose that was not given is never
 * simply deleted; it is marked with why.
 */
enum AdministrationStatus: string
{
    case Due = 'due';
    case Given = 'given';
    case Refused = 'refused';
    case Omitted = 'omitted';
    case Held = 'held';

    public function label(): string
    {
        return match ($this) {
            self::Due => 'مستحق',
            self::Given => 'أُعطي',
            self::Refused => 'رفضه المريض',
            self::Omitted => 'لم يُعطَ',
            self::Held => 'أُجّل بقرار سريري',
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Refused, self::Omitted, self::Held], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Due => [self::Given, self::Refused, self::Omitted, self::Held],
            // Once a dose is accounted for, the record stands.
            self::Given, self::Refused, self::Omitted, self::Held => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
