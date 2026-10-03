<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Domain\Events;

use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use Illuminate\Foundation\Events\Dispatchable;

final class EncounterStarted
{
    use Dispatchable;

    public function __construct(public readonly EncounterModel $encounter) {}
}
