<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Domain\Exceptions;

use App\Core\Exceptions\DomainException;
use App\Modules\Encounters\Domain\Enums\EncounterStatus;
use Symfony\Component\HttpFoundation\Response;

final class InvalidEncounterTransitionException extends DomainException
{
    protected int $status = Response::HTTP_CONFLICT;

    protected string $errorCode = 'invalid_encounter_transition';

    public static function between(EncounterStatus $from, EncounterStatus $to): self
    {
        return new self(
            "لا يمكن نقل الزيارة من حالة إلى الحالة المطلوبة.",
            [
                'from' => $from->value,
                'to' => $to->value,
                'allowed' => array_map(
                    static fn (EncounterStatus $status) => $status->value,
                    $from->allowedTransitions(),
                ),
            ],
        );
    }
}
