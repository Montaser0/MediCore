<?php

declare(strict_types=1);

namespace App\Modules\Encounters\Providers;

use App\Core\Support\Contracts\EncounterContext;
use App\Core\Support\Contracts\EncounterFactory;
use App\Core\Support\Contracts\TimelineRecorder;
use App\Core\Support\PatientRecordRegistry;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Encounters\Application\Actions\RecordTimelineEventAction;
use App\Modules\Encounters\Infrastructure\PatientRecord\EncountersRecordContributor;
use App\Modules\Encounters\Infrastructure\Services\EncounterContextService;
use App\Modules\Encounters\Infrastructure\Services\EncounterFactoryService;

final class EncountersServiceProvider extends ModuleServiceProvider
{
    public array $singletons = [
        TimelineRecorder::class => RecordTimelineEventAction::class,
        EncounterContext::class => EncounterContextService::class,
        EncounterFactory::class => EncounterFactoryService::class,
    ];

    protected function moduleName(): string
    {
        return 'Encounters';
    }

    protected function bootModule(): void
    {
        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(EncountersRecordContributor::class));
    }
}
