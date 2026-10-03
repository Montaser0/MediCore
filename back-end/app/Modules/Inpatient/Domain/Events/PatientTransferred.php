<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Domain\Events;

use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;
use Illuminate\Foundation\Events\Dispatchable;

final class PatientTransferred
{
    use Dispatchable;

    public function __construct(
        public readonly AdmissionModel $admission,
        public readonly ?BedModel $fromBed,
        public readonly BedModel $toBed,
    ) {}
}
