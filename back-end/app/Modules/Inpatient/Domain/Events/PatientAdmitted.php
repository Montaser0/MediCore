<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Domain\Events;

use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use Illuminate\Foundation\Events\Dispatchable;

final class PatientAdmitted
{
    use Dispatchable;

    public function __construct(public readonly AdmissionModel $admission) {}
}
