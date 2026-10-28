<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Queries;

use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Laboratory\Application\Support\LabBarcode;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderModel;
use App\Modules\Orders\Domain\Enums\OrderItemStatus;

/**
 * The slip the laboratory opens by scanning, or by typing the number under the bars.
 */
final class LabBarcodeQuery
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(string $barcode): ?array
    {
        $code = LabBarcode::digits($barcode);

        if ($code === '') {
            return null;
        }

        $labOrder = LabOrderModel::query()
            ->where('barcode', $code)
            ->whereHas('order', fn ($query) => $query->forCurrentHospital())
            ->with([
                'items.test',
                'order.items',
                'order.encounter.patient',
            ])
            ->first();

        if ($labOrder === null || $labOrder->order === null) {
            return null;
        }

        $order = $labOrder->order;
        $patient = $order->encounter?->patient;
        $names = $labOrder->items
            ->map(fn ($item) => $item->test?->name)
            ->filter()
            ->all();

        $tests = $labOrder->items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->test?->name,
            'code' => $item->test?->code,
            'sample_type' => $item->test?->sample_type,
            'unit' => $item->test?->unit,
            'price' => $item->test?->price,
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
        ])->values();

        foreach ($order->items as $item) {
            if (in_array($item->name, $names, true)) {
                continue;
            }

            $status = $item->status instanceof OrderItemStatus ? $item->status->value : (string) $item->status;

            $tests->push([
                'id' => null,
                'name' => $item->name,
                'code' => null,
                'sample_type' => null,
                'unit' => null,
                'price' => null,
                'status' => $status,
                'status_label' => 'بانتظار المختبر',
            ]);
        }

        return [
            'id' => $labOrder->id,
            'barcode' => $labOrder->barcode,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'clinical_notes' => $order->clinical_notes,
            'hospital_name' => HospitalModel::query()->whereKey($order->hospital_id)->value('name'),
            'clinic' => $labOrder->clinic_name,
            'doctor' => $labOrder->doctor_name,
            'patient' => $patient === null ? null : [
                'id' => $patient->id,
                'mrn' => $patient->mrn,
                'full_name' => $patient->full_name,
                'gender' => $patient->gender->value,
                'age' => $patient->age,
                'encounter_id' => $order->encounter_id,
            ],
            'tests' => $tests->all(),
        ];
    }
}
