<?php

declare(strict_types=1);

namespace App\Modules\Patients\Domain\Events;

use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Foundation\Events\Dispatchable;

final class PatientRegistered
{
    use Dispatchable;

    public function __construct(public readonly PatientModel $patient) {}
}
