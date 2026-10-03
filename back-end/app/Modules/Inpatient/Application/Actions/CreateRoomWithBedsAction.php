<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Inpatient\Domain\Enums\BedStatus;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\RoomModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\WardModel;
use Illuminate\Support\Facades\DB;

/**
 * A room is stored together with its beds. The bed count is the capacity,
 * and every new bed starts available.
 */
final class CreateRoomWithBedsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(WardModel $ward, array $data): RoomModel
    {
        $bedCount = (int) ($data['bed_count'] ?? $data['capacity'] ?? 0);
        $roomNumber = trim((string) ($data['room_number'] ?? ''));

        if ($roomNumber === '') {
            throw BusinessRuleException::make('رقم الغرفة مطلوب.');
        }

        if ($bedCount < 1 || $bedCount > 20) {
            throw BusinessRuleException::make('عدد الأسرة يجب أن يكون بين 1 و 20.');
        }

        $taken = RoomModel::query()
            ->where('ward_id', $ward->getKey())
            ->where('room_number', $roomNumber)
            ->exists();

        if ($taken) {
            throw BusinessRuleException::make('هذه الغرفة موجودة في هذا الجناح.');
        }

        return DB::transaction(function () use ($ward, $data, $roomNumber, $bedCount): RoomModel {
            $room = RoomModel::query()->create([
                'ward_id' => $ward->getKey(),
                'room_number' => $roomNumber,
                'room_type' => $data['room_type'] ?? null,
                'capacity' => $bedCount,
                'daily_rate' => $data['daily_rate'] ?? null,
            ]);

            for ($index = 1; $index <= $bedCount; $index++) {
                BedModel::query()->create([
                    'room_id' => $room->getKey(),
                    'bed_number' => $roomNumber.'-'.$index,
                    'status' => BedStatus::Available->value,
                ]);
            }

            return $room->load('beds');
        });
    }
}
