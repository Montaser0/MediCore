<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Laboratory\Domain\Enums\LabOrderItemStatus;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;

/**
 * The only writer of `lab_order_items.status`, so the workflow in the enum is
 * enforced rather than assumed.
 */
final class ChangeLabItemStatusAction
{
    public function execute(LabOrderItemModel $item, LabOrderItemStatus $target): LabOrderItemModel
    {
        if ($item->status === $target) {
            return $item;
        }

        if (! $item->status->canTransitionTo($target)) {
            throw BusinessRuleException::make(
                'لا يمكن تنفيذ هذا الإجراء والتحليل حالته «'.$item->status->label().'».',
                [
                    'allowed' => array_map(
                        static fn (LabOrderItemStatus $status) => $status->value,
                        $item->status->allowedTransitions(),
                    ),
                ],
            );
        }

        $item->forceFill(['status' => $target->value])->save();

        return $item;
    }
}
