<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Laboratory\Domain\Enums\BloodRequestStatus;
use App\Modules\Laboratory\Domain\Events\BloodRequestCancelled;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodRequestModel;

final class CancelBloodRequestAction
{
    public function execute(BloodRequestModel $request): BloodRequestModel
    {
        if ($request->status === BloodRequestStatus::Cancelled) {
            throw BusinessRuleException::make('هذا الطلب ملغى مسبقاً.');
        }

        $request->forceFill(['status' => BloodRequestStatus::Cancelled])->save();
        BloodRequestCancelled::dispatch($request);

        return $request->load(['patient', 'requestedBy.staff', 'confirmedBy.staff']);
    }
}
