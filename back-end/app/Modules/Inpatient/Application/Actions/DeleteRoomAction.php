<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Inpatient\Domain\Enums\BedStatus;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\RoomModel;

/** Hides a room from the ward board. A room with a patient stays in place. */
final class DeleteRoomAction
{
    public function execute(RoomModel $room): void
    {
        $room->load('beds.currentAssignment');

        $busy = $room->beds->contains(
            fn ($bed): bool => $bed->currentAssignment !== null || $bed->status === BedStatus::Occupied,
        );

        if ($busy) {
            throw BusinessRuleException::make('لا يمكن حذف غرفة فيها مريض.');
        }

        $room->forceFill(['is_active' => false])->save();
    }
}
