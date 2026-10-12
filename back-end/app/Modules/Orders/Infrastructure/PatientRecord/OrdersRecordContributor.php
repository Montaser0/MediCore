<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\PatientRecord;

use App\Core\Support\Contracts\PatientRecordContributor;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use Illuminate\Database\Eloquent\Builder;

final class OrdersRecordContributor implements PatientRecordContributor
{
    public function section(): string
    {
        return 'orders';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(int $patientId, ?int $encounterId = null): array
    {
        $base = OrderModel::query()->whereHas('encounter', function (Builder $query) use ($patientId, $encounterId): void {
            $query->where('patient_id', $patientId);

            if ($encounterId !== null) {
                $query->whereKey($encounterId);
            }
        });

        $recent = (clone $base)
            ->with(['items', 'orderedBy.staff', 'orderedBy.roles'])
            ->orderByDesc('ordered_at')
            ->limit(20)
            ->get();

        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->open()->count(),
            'recent' => $recent->map(fn (OrderModel $order) => [
                'id' => $order->getKey(),
                'order_number' => $order->order_number,
                'type' => $order->type->value,
                'status' => $order->status->value,
                'priority' => $order->priority->value,
                'items' => $order->items->pluck('name')->all(),
                'by' => $order->orderedBy?->actorLabel(),
                'ordered_at' => $order->ordered_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
