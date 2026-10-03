<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Inpatient\Domain\Enums\BedStatus;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\BedModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\RoomModel;
use App\Modules\Inpatient\Infrastructure\Persistence\Models\WardModel;
use Illuminate\Database\Seeder;

/**
 * التوزيع المكاني لأقسام التنويم. الغرف والأسرّة تُولَّد من سعة الغرفة حتى
 * تكون أرقام الأسرّة متوقّعة (101-1 و101-2 وهكذا).
 */
final class WardSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = HospitalModel::query()
            ->where('code', config('hms.default_hospital_code', 'MAIN'))
            ->firstOrFail();

        $wardDepartment = DepartmentModel::query()
            ->where('hospital_id', $hospital->getKey())
            ->where('code', 'WARD')
            ->firstOrFail();

        $wards = [
            // الاسم، الرمز، النوع، الطابق، أجرة الليلة، عدد الغرف، عدد الأسرّة في الغرفة
            ['قسم الباطنة', 'W-MED', 'عادي', 1, 40000, 6, 4],
            ['قسم الجراحة', 'W-SURG', 'عادي', 2, 40000, 6, 4],
            ['الجناح الخاص', 'W-PRIV', 'خاص', 3, 90000, 8, 1],
            ['العناية المشددة', 'W-ICU', 'عناية مشددة', 2, 250000, 1, 8],
            ['قسم التوليد', 'W-MAT', 'توليد', 1, 60000, 4, 2],
            ['قسم الأطفال', 'W-PED', 'أطفال', 1, 50000, 4, 3],
        ];

        foreach ($wards as [$name, $code, $type, $floor, $rate, $roomCount, $bedsPerRoom]) {
            $ward = WardModel::query()->firstOrCreate(
                ['hospital_id' => $hospital->getKey(), 'code' => $code],
                [
                    'department_id' => $wardDepartment->getKey(),
                    'name' => $name,
                    'type' => $type,
                    'floor' => $floor,
                    'daily_rate' => $rate,
                    'is_active' => true,
                ],
            );

            for ($index = 1; $index <= $roomCount; $index++) {
                $roomNumber = ($floor * 100) + $index;

                $room = RoomModel::query()->firstOrCreate(
                    ['ward_id' => $ward->getKey(), 'room_number' => (string) $roomNumber],
                    ['room_type' => $type, 'capacity' => $bedsPerRoom],
                );

                for ($bed = 1; $bed <= $bedsPerRoom; $bed++) {
                    BedModel::query()->firstOrCreate(
                        ['room_id' => $room->getKey(), 'bed_number' => $roomNumber.'-'.$bed],
                        ['status' => BedStatus::Available->value],
                    );
                }
            }
        }
    }
}
