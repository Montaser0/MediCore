<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Events\OrderCompleted;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of `orders.status`, so the transition table on the enum is
 * the real rule rather than a convention.
 */
final class ChangeOrderStatusAction
{
    public function execute(OrderModel $order, OrderStatus $target): OrderModel
    {
        if ($order->status === $target) {
            return $order;
        }

        if (! $order->status->canTransitionTo($target)) {
            throw BusinessRuleException::make(
                "لا يمكن تنفيذ هذا الإجراء والطلب {$order->order_number} حالته «{$order->status->label()}».",
                [
                    'allowed' => array_map(
                        static fn (OrderStatus $status) => $status->value,
                        $order->status->allowedTransitions(),
                    ),
                ],
            );
        }

        return DB::transaction(function () use ($order, $target): OrderModel {
            $order->forceFill([
                'status' => $target->value,
                'completed_at' => $target === OrderStatus::Completed ? now() : $order->completed_at,
            ])->save();

            if ($target === OrderStatus::Completed) {
                OrderCompleted::dispatch($order);
            }

            return $order;
        });
    }
}
