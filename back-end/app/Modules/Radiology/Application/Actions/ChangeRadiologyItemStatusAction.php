<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Radiology\Domain\Enums\RadiologyItemStatus;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;

final class ChangeRadiologyItemStatusAction
{
    public function execute(RadiologyOrderItemModel $item, RadiologyItemStatus $target): RadiologyOrderItemModel
    {
        if ($item->status === $target) {
            return $item;
        }

        if (! $item->status->canTransitionTo($target)) {
            throw BusinessRuleException::make(
                'لا يمكن تنفيذ هذا الإجراء وطلب الأشعة حالته «'.$item->status->label().'».',
                [
                    'allowed' => array_map(
                        static fn (RadiologyItemStatus $status) => $status->value,
                        $item->status->allowedTransitions(),
                    ),
                ],
            );
        }

        $item->forceFill(['status' => $target->value])->save();

        return $item;
    }
}
