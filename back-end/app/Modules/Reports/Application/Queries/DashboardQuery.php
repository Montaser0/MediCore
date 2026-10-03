<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application\Queries;

use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\InvoiceModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\PaymentModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Inpatient\Domain\Enums\BedStatus;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;
use App\Modules\Inventory\Application\Queries\StockAlertsQuery;
use App\Modules\Laboratory\Domain\Enums\LabOrderItemStatus;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use App\Modules\Operations\Domain\Enums\OperationStatus;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Radiology\Domain\Enums\RadiologyItemStatus;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;

/**
 * The single figures the management dashboard opens with. Deliberately made
 * of counts rather than rows, so it stays cheap enough to poll.
 */
final class DashboardQuery
{
    public function __construct(private readonly StockAlertsQuery $stockAlerts) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $today = now()->startOfDay();

        return [
            'generated_at' => now()->toIso8601String(),
            'patients' => [
                'total' => PatientModel::query()->forCurrentHospital()->count(),
                'registered_today' => PatientModel::query()->forCurrentHospital()->where('created_at', '>=', $today)->count(),
            ],
            'encounters' => [
                'open' => EncounterModel::query()->forCurrentHospital()->open()->count(),
                'today' => EncounterModel::query()->forCurrentHospital()->where('created_at', '>=', $today)->count(),
            ],
            'appointments' => [
                'today' => AppointmentModel::query()->forCurrentHospital()->whereDate('scheduled_at', $today)->count(),
                'upcoming' => AppointmentModel::query()->forCurrentHospital()->active()->where('scheduled_at', '>', now())->count(),
            ],
            'orders' => [
                'open' => OrderModel::query()->forCurrentHospital()->open()->count(),
                'pending_lab' => LabOrderItemModel::query()->whereIn('status', [
                    LabOrderItemStatus::Pending->value,
                    LabOrderItemStatus::SampleCollected->value,
                    LabOrderItemStatus::Processing->value,
                    LabOrderItemStatus::ResultReady->value,
                ])->count(),
                'pending_radiology' => RadiologyOrderItemModel::query()->whereIn('status', [
                    RadiologyItemStatus::Pending->value,
                    RadiologyItemStatus::Scheduled->value,
                    RadiologyItemStatus::InProgress->value,
                    RadiologyItemStatus::Completed->value,
                ])->count(),
            ],
            'inpatient' => $this->inpatient(),
            'operations' => [
                'scheduled_today' => OperationModel::query()->forCurrentHospital()->whereDate('scheduled_at', $today)->count(),
                'in_theatre' => OperationModel::query()->forCurrentHospital()->where('status', OperationStatus::InProgress->value)->count(),
            ],
            'finance' => [
                'revenue_today' => round((float) PaymentModel::query()->forCurrentHospital()->where('paid_at', '>=', $today)->sum('amount'), 2),
                'outstanding_balance' => round((float) InvoiceModel::query()->forCurrentHospital()->outstanding()->sum('balance'), 2),
                'unpaid_invoices' => InvoiceModel::query()->forCurrentHospital()->outstanding()->count(),
            ],
            'inventory_alerts' => $this->stockAlerts->counts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inpatient(): array
    {
        $totalBeds = BedModel::query()->count();
        $occupied = BedModel::query()->where('status', BedStatus::Occupied->value)->count();

        return [
            'admitted' => AdmissionModel::query()->forCurrentHospital()->active()->count(),
            'admitted_today' => AdmissionModel::query()->forCurrentHospital()->where('admitted_at', '>=', now()->startOfDay())->count(),
            'discharged_today' => AdmissionModel::query()->forCurrentHospital()->where('discharged_at', '>=', now()->startOfDay())->count(),
            'total_beds' => $totalBeds,
            'occupied_beds' => $occupied,
            'available_beds' => BedModel::query()->available()->count(),
            'occupancy_rate' => $totalBeds > 0 ? round($occupied / $totalBeds * 100, 1) : 0.0,
        ];
    }
}
