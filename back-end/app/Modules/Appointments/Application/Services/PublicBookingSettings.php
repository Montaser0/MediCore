<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Application\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Settings\Application\Services\SettingsRepository;
use Illuminate\Support\Carbon;

final class PublicBookingSettings
{
    public const GROUP = 'public_booking';

    public const KEY = 'config';

    public const TIMEZONE = 'Asia/Riyadh';

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return array{open: bool, opens_at: ?string, closes_at: ?string, clinics: array<int, array{clinic_id: int, capacity: int, doctor_ids: array<int, int>, times: array<int, string>}>}
     */
    public function get(): array
    {
        $stored = $this->settings->get(self::GROUP, self::KEY, null, $this->hospitalId());

        return $this->normalize(is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{open: bool, opens_at: ?string, closes_at: ?string, accepting: bool, clinics: array<int, array{clinic_id: int, capacity: int, doctor_ids: array<int, int>, times: array<int, string>}>}
     */
    public function save(array $input): array
    {
        $config = $this->normalize($input);
        $this->settings->set(self::GROUP, self::KEY, $config, $this->hospitalId(), 'array');

        return $this->present($config);
    }

    /**
     * @return array{open: bool, opens_at: ?string, closes_at: ?string, accepting: bool, clinics: array<int, array{clinic_id: int, capacity: int, doctor_ids: array<int, int>, times: array<int, string>}>}
     */
    public function present(?array $config = null): array
    {
        $config ??= $this->get();

        return [
            'open' => $config['open'],
            'opens_at' => $config['opens_at'],
            'closes_at' => $config['closes_at'],
            'accepting' => $this->accepting($config),
            'clinics' => $config['clinics'],
        ];
    }

    /**
     * @param  array{open: bool, opens_at: ?string, closes_at: ?string, clinics: array<int, array<string, mixed>>}  $config
     */
    public function accepting(array $config): bool
    {
        if ($config['open'] !== true) {
            return false;
        }

        if ($config['opens_at'] === null || $config['closes_at'] === null) {
            return true;
        }

        $now = Carbon::now(self::TIMEZONE)->format('H:i');

        return $now >= $config['opens_at'] && $now < $config['closes_at'];
    }

    public function closedMessage(array $config): string
    {
        if ($config['opens_at'] !== null && $config['closes_at'] !== null) {
            return 'تم إغلاق الحجز. يمكنكم الحجز من '.$config['opens_at'].' إلى '.$config['closes_at'].'.';
        }

        return 'تم إغلاق الحجز.';
    }

    /**
     * @return array{open: bool, message: ?string, clinics: array<int, array<string, mixed>>}
     */
    public function availability(string $date): array
    {
        $config = $this->get();

        if (! $this->accepting($config)) {
            return $this->availabilityPayload($config, false, $this->closedMessage($config), []);
        }

        $day = Carbon::parse($date, self::TIMEZONE)->startOfDay();
        $today = Carbon::now(self::TIMEZONE)->startOfDay();

        if ($day->lt($today)) {
            return $this->availabilityPayload($config, true, 'اختر تاريخاً من اليوم فصاعداً.', []);
        }

        return $this->availabilityPayload($config, true, null, $this->clinicsForDay($config, $day));
    }

    /**
     * @param  array{opens_at: ?string, closes_at: ?string}  $config
     * @param  array<int, array<string, mixed>>  $clinics
     * @return array{open: bool, opens_at: ?string, closes_at: ?string, message: ?string, clinics: array<int, array<string, mixed>>}
     */
    private function availabilityPayload(array $config, bool $open, ?string $message, array $clinics): array
    {
        return [
            'open' => $open,
            'opens_at' => $config['opens_at'],
            'closes_at' => $config['closes_at'],
            'message' => $message,
            'clinics' => $clinics,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{clinic: ClinicModel, scheduled_at: Carbon, doctor_id: int}
     */
    public function assertBookable(array $data): array
    {
        $config = $this->get();

        if (! $this->accepting($config)) {
            throw BusinessRuleException::make($this->closedMessage($config));
        }

        $clinicId = (int) $data['clinic_id'];
        $doctorId = (int) $data['doctor_id'];
        $scheduledAt = Carbon::parse((string) $data['scheduled_at'], self::TIMEZONE);
        $rule = $this->clinicRule($config, $clinicId);

        if ($rule === null) {
            throw BusinessRuleException::make('هذه العيادة غير متاحة للحجز.');
        }

        $time = $scheduledAt->format('H:i');

        if (! in_array($time, $rule['times'], true)) {
            throw BusinessRuleException::make('هذا الوقت غير متاح في العيادة.');
        }

        if (! in_array($doctorId, $rule['doctor_ids'], true)) {
            throw BusinessRuleException::make('هذا الطبيب غير متاح في هذه العيادة.');
        }

        if ($scheduledAt->lte(Carbon::now(self::TIMEZONE))) {
            throw BusinessRuleException::make('هذا الوقت مضى. اختر وقتاً لاحقاً.');
        }

        $clinic = ClinicModel::query()
            ->with('department')
            ->active()
            ->whereHas('department', fn ($query) => $query->where('is_active', true))
            ->find($clinicId);

        if ($clinic === null || $clinic->department === null) {
            throw BusinessRuleException::make('العيادة المختارة غير متاحة.');
        }

        return [
            'clinic' => $clinic,
            'scheduled_at' => $scheduledAt,
            'doctor_id' => $doctorId,
            'capacity' => $rule['capacity'],
        ];
    }

    public function guardCapacity(int $clinicId, Carbon $scheduledAt, int $doctorId, int $capacity): void
    {
        $start = $scheduledAt->copy()->timezone(self::TIMEZONE)->startOfDay()->utc();
        $end = $scheduledAt->copy()->timezone(self::TIMEZONE)->endOfDay()->utc();

        $rows = AppointmentModel::query()
            ->active()
            ->where('clinic_id', $clinicId)
            ->whereBetween('scheduled_at', [$start, $end])
            ->lockForUpdate()
            ->get(['doctor_id', 'scheduled_at']);

        if ($rows->count() >= $capacity) {
            throw BusinessRuleException::make('اكتملت سعة هذه العيادة لهذا اليوم.');
        }

        $time = $scheduledAt->copy()->timezone(self::TIMEZONE)->format('H:i');
        $doctorTaken = $rows->contains(function (AppointmentModel $appointment) use ($doctorId, $time): bool {
            return (int) $appointment->doctor_id === $doctorId
                && $appointment->scheduled_at->copy()->timezone(self::TIMEZONE)->format('H:i') === $time;
        });

        if ($doctorTaken) {
            throw BusinessRuleException::make('هذا الطبيب لديه موعد في الوقت نفسه.');
        }
    }

    /**
     * @param  array{clinics: array<int, array{clinic_id: int, capacity: int, doctor_ids: array<int, int>, times: array<int, string>}>}  $config
     * @return array{clinic_id: int, capacity: int, doctor_ids: array<int, int>, times: array<int, string>}|null
     */
    private function clinicRule(array $config, int $clinicId): ?array
    {
        foreach ($config['clinics'] as $clinic) {
            if ($clinic['clinic_id'] === $clinicId) {
                return $clinic;
            }
        }

        return null;
    }

    /**
     * @param  array{clinics: array<int, array{clinic_id: int, capacity: int, doctor_ids: array<int, int>, times: array<int, string>}>}  $config
     * @return array<int, array<string, mixed>>
     */
    private function clinicsForDay(array $config, Carbon $day): array
    {
        if ($config['clinics'] === []) {
            return [];
        }

        $clinicIds = array_column($config['clinics'], 'clinic_id');
        $clinics = ClinicModel::query()
            ->with('department:id,name,is_active')
            ->active()
            ->whereIn('id', $clinicIds)
            ->whereHas('department', fn ($query) => $query->where('is_active', true))
            ->get()
            ->keyBy('id');

        $doctorIds = [];
        foreach ($config['clinics'] as $clinic) {
            foreach ($clinic['doctor_ids'] as $doctorId) {
                $doctorIds[$doctorId] = $doctorId;
            }
        }

        $doctors = DoctorModel::query()
            ->with('staff:id,first_name,last_name,is_active')
            ->whereIn('id', array_values($doctorIds))
            ->get()
            ->keyBy('id');

        $start = $day->copy()->startOfDay()->utc();
        $end = $day->copy()->endOfDay()->utc();
        $now = Carbon::now(self::TIMEZONE);

        $appointments = AppointmentModel::query()
            ->active()
            ->whereIn('clinic_id', $clinicIds)
            ->whereBetween('scheduled_at', [$start, $end])
            ->get(['clinic_id', 'doctor_id', 'scheduled_at'])
            ->groupBy(fn (AppointmentModel $appointment) => (int) $appointment->clinic_id);

        $rows = [];

        foreach ($config['clinics'] as $rule) {
            $clinic = $clinics->get($rule['clinic_id']);
            if ($clinic === null) {
                continue;
            }

            $bookedRows = $appointments->get($rule['clinic_id'], collect());
            $booked = $bookedRows->count();
            $full = $booked >= $rule['capacity'];
            $times = [];

            if (! $full) {
                foreach ($rule['times'] as $time) {
                    $slot = Carbon::parse($day->toDateString().' '.$time, self::TIMEZONE);
                    if ($slot->lte($now)) {
                        continue;
                    }

                    $busy = $bookedRows
                        ->filter(fn (AppointmentModel $appointment) => $appointment->scheduled_at->copy()->timezone(self::TIMEZONE)->format('H:i') === $time)
                        ->map(fn (AppointmentModel $appointment) => (int) $appointment->doctor_id)
                        ->all();
                    $free = array_values(array_filter(
                        $rule['doctor_ids'],
                        fn (int $doctorId) => ! in_array($doctorId, $busy, true)
                            && $doctors->has($doctorId)
                            && $doctors->get($doctorId)?->staff?->is_active !== false,
                    ));

                    if ($free === []) {
                        continue;
                    }

                    $times[] = ['time' => $time, 'doctor_ids' => $free];
                }
            }

            $rows[] = [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'department' => $clinic->department?->name,
                'capacity' => $rule['capacity'],
                'booked' => $booked,
                'remaining' => max(0, $rule['capacity'] - $booked),
                'full' => $full || $times === [],
                'doctors' => collect($rule['doctor_ids'])
                    ->map(function (int $doctorId) use ($doctors) {
                        $doctor = $doctors->get($doctorId);
                        if ($doctor === null || $doctor->staff?->is_active === false) {
                            return null;
                        }

                        return [
                            'id' => $doctor->id,
                            'name' => $doctor->staff?->full_name ?: 'طبيب '.$doctor->id,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all(),
                'times' => $times,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{open: bool, opens_at: ?string, closes_at: ?string, clinics: array<int, array{clinic_id: int, capacity: int, doctor_ids: array<int, int>, times: array<int, string>}>}
     */
    private function normalize(array $input): array
    {
        $clinics = [];
        $seen = [];

        foreach ($input['clinics'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $clinicId = (int) ($row['clinic_id'] ?? 0);
            if ($clinicId < 1 || isset($seen[$clinicId])) {
                continue;
            }
            $seen[$clinicId] = true;

            $times = [];
            foreach ($row['times'] ?? [] as $time) {
                $normalized = $this->timeOrNull($time);
                if ($normalized !== null) {
                    $times[$normalized] = $normalized;
                }
            }
            ksort($times);

            $doctorIds = [];
            foreach ($row['doctor_ids'] ?? [] as $doctorId) {
                $doctorId = (int) $doctorId;
                if ($doctorId > 0) {
                    $doctorIds[$doctorId] = $doctorId;
                }
            }

            $clinics[] = [
                'clinic_id' => $clinicId,
                'capacity' => max(1, (int) ($row['capacity'] ?? 1)),
                'doctor_ids' => array_values($doctorIds),
                'times' => array_values($times),
            ];
        }

        return [
            'open' => filter_var($input['open'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'opens_at' => $this->timeOrNull($input['opens_at'] ?? null),
            'closes_at' => $this->timeOrNull($input['closes_at'] ?? null),
            'clinics' => $clinics,
        ];
    }

    private function timeOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $parsed = Carbon::createFromFormat('!H:i', substr($value, 0, 5));

        return $parsed === false ? null : $parsed->format('H:i');
    }

    /**
     * @return array{open: bool, opens_at: null, closes_at: null, clinics: array{}}
     */
    private function defaults(): array
    {
        return [
            'open' => false,
            'opens_at' => null,
            'closes_at' => null,
            'clinics' => [],
        ];
    }

    private function hospitalId(): int
    {
        $code = (string) config('hms.default_hospital_code', 'MAIN');
        $id = HospitalModel::query()->where('code', $code)->value('id')
            ?? HospitalModel::query()->orderBy('id')->value('id');

        if ($id === null) {
            throw BusinessRuleException::make('لا يوجد مستشفى لإعداد الحجز.');
        }

        return (int) $id;
    }
}
