<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Domain\Events;

use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterTransferModel;
use Illuminate\Foundation\Events\Dispatchable;

final class EncounterTransferred
{
    use Dispatchable;

    public function __construct(
        public readonly EncounterModel $encounter,
        public readonly EncounterTransferModel $transfer,
    ) {}
}
