<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Inpatient\Domain\Enums\BedStatus;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;

/**
 * The only writer of a bed's status. Housekeeping and maintenance go through
 * here, as do the admission actions, so a bed can never skip its cleaning
 * step on the way from one patient to the next.
 */
final class ChangeBedStatusAction
{
    public function execute(BedModel $bed, BedStatus $target, ?string $notes = null): BedModel
    {
        $current = $bed->status;

        if ($current === $target) {
            return $bed;
        }

        if (! $current->canTransitionTo($target)) {
            throw BusinessRuleException::make(
                "لا يمكن نقل السرير {$bed->bed_number} من «{$current->label()}» إلى «{$target->label()}».",
                ['bed_id' => $bed->getKey(), 'from' => $current->value, 'to' => $target->value],
            );
        }

        if ($current === BedStatus::Occupied && $bed->currentAssignment()->exists()) {
            throw BusinessRuleException::make(
                "السرير {$bed->bed_number} ما زال عليه مريض. أخرج المريض أو انقله أولاً.",
                ['bed_id' => $bed->getKey()],
            );
        }

        $bed->forceFill(array_filter([
            'status' => $target->value,
            'notes' => $notes,
        ], static fn ($value) => $value !== null))->save();

        return $bed;
    }
}
