<?php

declare(strict_types=1);

namespace App\Modules\Documents\Providers;

use App\Core\Support\Contracts\DocumentService;
use App\Core\Support\PatientRecordRegistry;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Documents\Application\Actions\UploadDocumentAction;
use App\Modules\Documents\Infrastructure\PatientRecord\DocumentsRecordContributor;

final class DocumentsServiceProvider extends ModuleServiceProvider
{
    public array $singletons = [
        DocumentService::class => UploadDocumentAction::class,
    ];

    protected function moduleName(): string
    {
        return 'Documents';
    }

    protected function bootModule(): void
    {
        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(DocumentsRecordContributor::class));
    }
}
