<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application\Queries;

use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;
use App\Modules\Operations\Infrastructure\Persistence\Models\OperationModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationDispenseModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Clinical throughput for a period: who came, what was wrong with them, and
 * how much work each department did.
 */
final class ActivityReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $encounters = EncounterModel::query()->forCurrentHospital()->whereBetween('created_at', [$from, $to]);
        $admissions = AdmissionModel::query()->forCurrentHospital()->whereBetween('admitted_at', [$from, $to]);
        $discharged = AdmissionModel::query()->forCurrentHospital()->whereBetween('discharged_at', [$from, $to])->get();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'patients' => [
                'new_registrations' => PatientModel::query()->forCurrentHospital()->whereBetween('created_at', [$from, $to])->count(),
            ],
            'encounters' => [
                'total' => (clone $encounters)->count(),
                'by_type' => (clone $encounters)
                    ->select('type', DB::raw('COUNT(*) as count'))
                    ->groupBy('type')
                    ->get()
                    ->map(fn ($row) => ['type' => (string) $row->type, 'count' => (int) $row->count])
                    ->all(),
                'by_department' => (clone $encounters)
                    ->with('department')
                    ->select('department_id', DB::raw('COUNT(*) as count'))
                    ->whereNotNull('department_id')
                    ->groupBy('department_id')
                    ->orderByDesc('count')
                    ->get()
                    ->map(fn ($row) => [
                        'department_id' => (int) $row->department_id,
                        'department' => $row->department?->name,
                        'count' => (int) $row->count,
                    ])
                    ->all(),
                'by_day' => (clone $encounters)
                    ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                    ->groupBy('day')
                    ->orderBy('day')
                    ->get()
                    ->map(fn ($row) => ['day' => (string) $row->day, 'count' => (int) $row->count])
                    ->all(),
            ],
            'inpatient' => [
                'admissions' => (clone $admissions)->count(),
                'discharges' => $discharged->count(),
                // Averaging the actual stays rather than dividing totals, so
                // a long stay does not distort a short reporting window.
                'average_length_of_stay' => $discharged->isEmpty()
                    ? 0.0
                    : round($discharged->avg(fn (AdmissionModel $a) => $a->lengthOfStayDays()), 1),
            ],
            'departments' => [
                'lab_tests' => LabOrderItemModel::query()->whereBetween('created_at', [$from, $to])->count(),
                'radiology_studies' => RadiologyOrderItemModel::query()->whereBetween('created_at', [$from, $to])->count(),
                'dispenses' => MedicationDispenseModel::query()->forCurrentHospital()->whereBetween('dispensed_at', [$from, $to])->count(),
                'operations' => OperationModel::query()->forCurrentHospital()->whereBetween('scheduled_at', [$from, $to])->count(),
            ],
            'top_diagnoses' => DiagnosisModel::query()
                ->with('code')
                ->whereBetween('created_at', [$from, $to])
                ->select('diagnosis_code_id', DB::raw('COUNT(*) as count'))
                ->whereNotNull('diagnosis_code_id')
                ->groupBy('diagnosis_code_id')
                ->orderByDesc('count')
                ->limit(15)
                ->get()
                ->map(fn ($row) => [
                    'code' => $row->code?->code,
                    'title' => $row->code?->title,
                    'count' => (int) $row->count,
                ])
                ->all(),
        ];
    }
}
