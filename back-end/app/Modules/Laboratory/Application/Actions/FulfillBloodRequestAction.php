<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Modules\Laboratory\Domain\Enums\BloodRequestStatus;
use App\Modules\Laboratory\Domain\Events\BloodRequestFulfilled;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodRequestModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class FulfillBloodRequestAction
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(BloodRequestModel $request, array $data): BloodRequestModel
    {
        $hospitalId = $this->currentUser->hospitalId();

        if ($hospitalId !== null && (int) $request->hospital_id !== $hospitalId) {
            throw (new ModelNotFoundException)->setModel(BloodRequestModel::class, [$request->getKey()]);
        }

        return DB::transaction(function () use ($request, $data): BloodRequestModel {
            $request->forceFill([
                'confirmed_blood_group' => $data['confirmed_blood_group'],
                'bag_number' => $data['bag_number'],
                'issued_at' => $data['issued_at'],
                'status' => BloodRequestStatus::Fulfilled,
                'confirmed_by' => $this->currentUser->id(),
                'confirmed_at' => now(),
            ])->save();

            BloodRequestFulfilled::dispatch($request);

            return $request->load(['patient', 'requestedBy.staff', 'confirmedBy.staff']);
        });
    }
}
