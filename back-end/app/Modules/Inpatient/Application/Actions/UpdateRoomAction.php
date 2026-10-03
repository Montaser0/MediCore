<?php

declare(strict_types=1);

namespace App\Modules\Inpatient\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Inpatient\Domain\Enums\BedStatus;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\RoomModel;
use Illuminate\Support\Facades\DB;

/**
 * Changes a room's place, price and bed count. Beds that already held a
 * patient are kept, so past stays still point at a real bed.
 */
final class UpdateRoomAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(RoomModel $room, array $data): RoomModel
    {
        $bedCount = (int) ($data['bed_count'] ?? $data['capacity'] ?? 0);
        $roomNumber = trim((string) ($data['room_number'] ?? ''));
        $wardId = (int) ($data['ward_id'] ?? $room->ward_id);

        if ($roomNumber === '') {
            throw BusinessRuleException::make('رقم الغرفة مطلوب.');
        }

        if ($bedCount < 1 || $bedCount > 20) {
            throw BusinessRuleException::make('عدد الأسرة يجب أن يكون بين 1 و 20.');
        }

        $taken = RoomModel::query()
            ->where('ward_id', $wardId)
            ->where('room_number', $roomNumber)
            ->whereKeyNot($room->getKey())
            ->exists();

        if ($taken) {
            throw BusinessRuleException::make('هذه الغرفة موجودة في هذا الجناح.');
        }

        return DB::transaction(function () use ($room, $data, $roomNumber, $wardId, $bedCount): RoomModel {
            $room->fill([
                'ward_id' => $wardId,
                'room_number' => $roomNumber,
                'room_type' => $data['room_type'] ?? null,
                'capacity' => $bedCount,
                'daily_rate' => $data['daily_rate'] ?? null,
            ])->save();

            $this->syncBeds($room, $roomNumber, $bedCount);

            return $room->fresh('beds');
        });
    }

    private function syncBeds(RoomModel $room, string $roomNumber, int $bedCount): void
    {
        $beds = $room->beds()->withCount('assignments')->orderBy('id')->get();
        $current = $beds->count();

        if ($bedCount > $current) {
            $used = $beds->pluck('bed_number');
            $added = 0;
            $serial = 1;

            while ($added < $bedCount - $current) {
                $number = $roomNumber.'-'.$serial;
                $serial++;

                if ($used->contains($number)) {
                    continue;
                }

                if ($serial > 40) {
                    throw BusinessRuleException::make('تعذر إضافة أسرة جديدة لهذه الغرفة.');
                }

                BedModel::query()->create([
                    'room_id' => $room->getKey(),
                    'bed_number' => $number,
                    'status' => BedStatus::Available->value,
                ]);
                $used->push($number);
                $added++;
            }

            return;
        }

        if ($bedCount === $current) {
            return;
        }

        $spare = $beds
            ->reverse()
            ->filter(fn (BedModel $bed): bool => (int) $bed->assignments_count === 0
                && $bed->status === BedStatus::Available)
            ->values();

        $remove = $current - $bedCount;

        if ($spare->count() < $remove) {
            throw BusinessRuleException::make('لا يمكن إنقاص الأسرة لأن بعضها مشغول أو مستخدم سابقاً.');
        }

        foreach ($spare->take($remove) as $bed) {
            $bed->delete();
        }
    }
}
