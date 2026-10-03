<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Support\Contracts\OrderFulfiller;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use App\Modules\Pharmacy\Domain\Enums\PrescriptionStatus;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionModel;

/**
 * A `pharmacy` order raised through the order engine becomes a prescription
 * the pharmacy can dispense against.
 */
final class FulfilPharmacyOrderAction implements OrderFulfiller
{
    public function __construct(private readonly CreatePrescriptionAction $createPrescription) {}

    public function orderType(): string
    {
        return 'pharmacy';
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function fulfil(int $orderId, array $items): void
    {
        $order = OrderModel::query()->find($orderId);

        if ($order === null) {
            return;
        }

        $lines = [];

        foreach ($items as $item) {
            $medication = $this->resolveMedication($item);

            if ($medication === null) {
                continue;
            }

            $lines[] = [
                'medication_id' => $medication->getKey(),
                'quantity' => $item['quantity'] ?? null,
                'instructions' => $item['instructions'] ?? null,
                'route' => $medication->route,
            ];
        }

        if ($lines === []) {
            return;
        }

        $this->createPrescription->execute([
            'encounter_id' => $order->encounter_id,
            'order_id' => $order->getKey(),
            'notes' => $order->clinical_notes,
            'items' => $lines,
        ]);
    }

    public function cancel(int $orderId, ?string $reason = null): void
    {
        PrescriptionModel::query()
            ->where('order_id', $orderId)
            ->dispensable()
            ->update([
                'status' => PrescriptionStatus::Cancelled->value,
                'cancellation_reason' => $reason,
            ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveMedication(array $item): ?MedicationModel
    {
        if (! empty($item['service_id'])) {
            $medication = MedicationModel::query()->find($item['service_id']);

            if ($medication !== null) {
                return $medication;
            }
        }

        $name = (string) ($item['name'] ?? '');

        if ($name === '') {
            return null;
        }

        return MedicationModel::query()
            ->where('name', $name)
            ->orWhere('barcode', $name)
            ->first();
    }
}
