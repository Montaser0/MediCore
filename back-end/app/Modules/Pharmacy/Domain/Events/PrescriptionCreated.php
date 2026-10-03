<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Events;

use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionModel;
use Illuminate\Foundation\Events\Dispatchable;

final class PrescriptionCreated
{
    use Dispatchable;

    public function __construct(public readonly PrescriptionModel $prescription) {}
}
