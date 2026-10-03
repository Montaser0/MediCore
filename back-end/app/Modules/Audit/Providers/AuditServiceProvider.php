<?php

declare(strict_types=1);

namespace App\Modules\Audit\Providers;

use App\Core\Support\Contracts\AuditRecorder;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Audit\Application\Actions\RecordAuditAction;

final class AuditServiceProvider extends ModuleServiceProvider
{
    public array $singletons = [
        AuditRecorder::class => RecordAuditAction::class,
    ];

    protected function moduleName(): string
    {
        return 'Audit';
    }
}
