<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Core\Support\PatientRecordRegistry;
use App\Core\Support\Providers\ModuleServiceProvider;
use App\Modules\Billing\Application\Listeners\ChargeCompletedOperation;
use App\Modules\Billing\Application\Listeners\ChargeCompletedRadiologyReport;
use App\Modules\Billing\Application\Listeners\ChargeCompletedServiceOrder;
use App\Modules\Billing\Application\Listeners\ChargeDispensedMedication;
use App\Modules\Billing\Application\Listeners\ChargeInpatientStay;
use App\Modules\Billing\Application\Listeners\ChargePerformedProcedure;
use App\Modules\Billing\Application\Listeners\ChargeStartedEncounter;
use App\Modules\Billing\Application\Listeners\ChargeVerifiedLabResult;
use App\Modules\Billing\Application\Listeners\IssueClosedEncounterInvoice;
use App\Modules\Billing\Domain\Events\ChargeRaised;
use App\Modules\Billing\Infrastructure\PatientRecord\BillingRecordContributor;
use App\Modules\Clinical\Domain\Events\ProcedurePerformed;
use App\Modules\Encounters\Domain\Events\EncounterCompleted;
use App\Modules\Encounters\Domain\Events\EncounterStarted;
use App\Modules\Inpatient\Domain\Events\PatientDischarged;
use App\Modules\Laboratory\Domain\Events\LabResultVerified;
use App\Modules\Operations\Domain\Events\OperationCompleted;
use App\Modules\Orders\Domain\Events\OrderCompleted;
use App\Modules\Pharmacy\Domain\Events\MedicationDispensed;
use App\Modules\Radiology\Domain\Events\ReportCompleted;

final class BillingServiceProvider extends ModuleServiceProvider
{
    /**
     * Automatic charging lives here rather than in the clinical modules: a
     * department announces that work was done, and billing decides what it
     * costs.
     */
    protected array $listen = [
        MedicationDispensed::class => [ChargeDispensedMedication::class],
        LabResultVerified::class => [ChargeVerifiedLabResult::class],
        ReportCompleted::class => [ChargeCompletedRadiologyReport::class],
        PatientDischarged::class => [ChargeInpatientStay::class],
        OperationCompleted::class => [ChargeCompletedOperation::class],
        EncounterStarted::class => [ChargeStartedEncounter::class],
        ProcedurePerformed::class => [ChargePerformedProcedure::class],
        OrderCompleted::class => [ChargeCompletedServiceOrder::class],
        EncounterCompleted::class => [IssueClosedEncounterInvoice::class],
        ChargeRaised::class => [IssueClosedEncounterInvoice::class],
    ];

    protected function moduleName(): string
    {
        return 'Billing';
    }

    protected function bootModule(): void
    {
        $this->app->make(PatientRecordRegistry::class)
            ->register($this->app->make(BillingRecordContributor::class));
    }
}
