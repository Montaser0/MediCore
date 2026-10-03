<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Inpatient\Domain\Enums\BedStatus;
use App\Modules\Inpatient\Domain\Exceptions\BedUnavailableException;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\AdmissionModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedAssignmentModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;
use Illuminate\Support\Facades\DB;

/**
 * Puts a patient in a bed. The bed row is locked first, because two clerks
 * looking at the same ward board will otherwise both pick the last free bed.
 *
 * Callers are expected to already be inside a transaction when this is part
 * of an admission or transfer; the extra transaction here nests safely.
 */
final class AssignBedAction
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    public function execute(AdmissionModel $admission, int $bedId, ?string $reason = null): BedAssignmentModel
    {
        if (! $admission->status->isActive()) {
            throw BusinessRuleException::make(
                "لا يمكن تعيين سرير والتنويم {$admission->admission_number} حالته «{$admission->status->label()}».",
                ['admission_id' => $admission->getKey()],
            );
        }

        return DB::transaction(function () use ($admission, $bedId, $reason): BedAssignmentModel {
            /** @var BedModel $bed */
            $bed = BedModel::query()->lockForUpdate()->findOrFail($bedId);

            if (! $bed->status->isAssignable()) {
                throw BedUnavailableException::for($bed->bed_number, $bed->status->value);
            }

            // Belt and braces: an assignable status should never coexist with
            // an open assignment, but the check costs nothing.
            if ($bed->currentAssignment()->exists()) {
                throw BedUnavailableException::for($bed->bed_number, BedStatus::Occupied->value);
            }

            $assignment = BedAssignmentModel::query()->create([
                'admission_id' => $admission->getKey(),
                'bed_id' => $bed->getKey(),
                'assigned_at' => now(),
                'assigned_by' => $this->currentUser->id(),
                'reason' => $reason,
            ]);

            $bed->forceFill(['status' => BedStatus::Occupied->value])->save();

            return $assignment->load('bed.room.ward');
        });
    }

    /**
     * Frees whichever bed the admission currently holds and sends it for
     * cleaning rather than straight back to the free pool.
     */
    public function release(AdmissionModel $admission): ?BedAssignmentModel
    {
        $assignment = $admission->currentBedAssignment;

        if ($assignment === null) {
            return null;
        }

        $assignment->forceFill(['released_at' => now()])->save();

        $bed = BedModel::query()->lockForUpdate()->find($assignment->bed_id);

        if ($bed !== null) {
            $bed->forceFill(['status' => BedStatus::Cleaning->value])->save();
        }

        return $assignment;
    }
}
