<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain\Enums;

/**
 * Who stands where in theatre. The surgeon and the anaesthetist are the two
 * roles an operation cannot start without.
 */
enum OperationRole: string
{
    case Surgeon = 'surgeon';
    case AssistantSurgeon = 'assistant_surgeon';
    case Anesthetist = 'anesthetist';
    case ScrubNurse = 'scrub_nurse';
    case CirculatingNurse = 'circulating_nurse';
    case Technician = 'technician';
    case Observer = 'observer';

    public function label(): string
    {
        return match ($this) {
            self::Surgeon => 'جراح',
            self::AssistantSurgeon => 'جراح مساعد',
            self::Anesthetist => 'طبيب تخدير',
            self::ScrubNurse => 'ممرض عمليات',
            self::CirculatingNurse => 'ممرض دوار',
            self::Technician => 'فني',
            self::Observer => 'مراقب',
        };
    }

    public function isRequired(): bool
    {
        return in_array($this, [self::Surgeon, self::Anesthetist], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
