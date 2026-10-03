<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Laboratory\Domain\Enums\LabOrderItemStatus;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;

/**
 * Cancels a single test on a request. A verified result cannot be cancelled:
 * the record of what was reported has to stay.
 */
final class CancelLabOrderItemAction
{
    public function __construct(private readonly ChangeLabItemStatusAction $changeStatus) {}

    public function execute(LabOrderItemModel $item, ?string $reason = null): LabOrderItemModel
    {
        if ($item->status === LabOrderItemStatus::Verified) {
            throw BusinessRuleException::make('لا يمكن إلغاء نتيجة معتمدة.');
        }

        $item = $this->changeStatus->execute($item, LabOrderItemStatus::Cancelled);

        if ($reason !== null) {
            $item->result?->forceFill(['comment' => trim(($item->result->comment ?? '')."\nCancelled: ".$reason)])->save();
        }

        return $item;
    }
}
