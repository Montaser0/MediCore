<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Laboratory\Domain\Enums\LabOrderItemStatus;
use App\Modules\Laboratory\Domain\Events\LabOrderItemCancelled;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderItemModel;

/**
 * Cancels a single test and drops its charge from the patient account.
 */
final class CancelLabOrderItemAction
{
    public function __construct(private readonly ChangeLabItemStatusAction $changeStatus) {}

    public function execute(LabOrderItemModel $item, ?string $reason = null): LabOrderItemModel
    {
        if ($item->status === LabOrderItemStatus::Cancelled) {
            throw BusinessRuleException::make('هذا الطلب ملغى مسبقاً.');
        }

        $item = $this->changeStatus->execute($item, LabOrderItemStatus::Cancelled);

        if ($reason !== null) {
            $item->result?->forceFill(['comment' => trim(($item->result->comment ?? '')."\nCancelled: ".$reason)])->save();
        }

        LabOrderItemCancelled::dispatch($item);

        return $item;
    }
}
