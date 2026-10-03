<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Domain\Enums;

/**
 * Part of the status engine the PRD asks for: the allowed moves are declared
 * here once instead of being re-checked with ad-hoc conditionals.
 */
enum EncounterStatus: string
{
    case Registered = 'registered';
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Registered => [self::Waiting, self::InProgress, self::Cancelled],
            self::Waiting => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * An open visit is one that can still accept orders, notes and charges.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Registered, self::Waiting, self::InProgress], true);
    }

    public function isTerminal(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
