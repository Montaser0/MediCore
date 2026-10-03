<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Domain\Events;

use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyReportModel;
use Illuminate\Foundation\Events\Dispatchable;

final class ReportCompleted
{
    use Dispatchable;

    public function __construct(public readonly RadiologyReportModel $report) {}
}
