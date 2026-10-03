<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Services;

use App\Core\Support\Contracts\OrderCoordinator;
use App\Modules\Orders\Application\Actions\ChangeOrderStatusAction;
use App\Modules\Orders\Application\Actions\CreateOrderAction;
use App\Modules\Orders\Domain\Enums\OrderItemStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItemModel;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;

final class OrderCoordinatorService implements OrderCoordinator
{
    public function __construct(
        private readonly CreateOrderAction $createOrder,
        private readonly ChangeOrderStatusAction $changeStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function place(int $encounterId, string $type, array $data): int
    {
        $order = $this->createOrder->execute([
            'encounter_id' => $encounterId,
            'type' => $type,
        ] + $data);

        return $order->getKey();
    }

    public function markInProgress(int $orderId): void
    {
        $order = OrderModel::query()->findOrFail($orderId);

        if ($order->status === OrderStatus::Pending) {
            $order = $this->changeStatus->execute($order, OrderStatus::Accepted);
        }

        $this->changeStatus->execute($order, OrderStatus::InProgress);
    }

    public function markCompleted(int $orderId): void
    {
        $order = OrderModel::query()->findOrFail($orderId);

        if ($order->status === OrderStatus::Pending) {
            $order = $this->changeStatus->execute($order, OrderStatus::InProgress);
        }

        $this->changeStatus->execute($order, OrderStatus::Completed);
    }

    /**
     * A department finished one line of a multi-item order. When the last open
     * item closes, the order itself completes.
     */
    public function markItemCompleted(int $orderItemId): void
    {
        $item = OrderItemModel::query()->with('order')->findOrFail($orderItemId);

        $item->forceFill(['status' => OrderItemStatus::Completed->value])->save();

        $order = $item->order;

        if ($order === null) {
            return;
        }

        if (! $order->hasOpenItems() && $order->status->isOpen()) {
            $this->markCompleted($order->getKey());
        }
    }
}
