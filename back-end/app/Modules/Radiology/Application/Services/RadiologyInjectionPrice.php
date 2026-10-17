<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Application\Services;

use App\Core\Auth\CurrentUser;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Settings\Application\Services\SettingsRepository;

/**
 * One injection price for every radiology order. It is chosen in the
 * radiology catalogue and added once when an order is marked with injection.
 */
final class RadiologyInjectionPrice
{
    public const GROUP = 'radiology';

    public const KEY = 'injection_price';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly CurrentUser $currentUser,
    ) {}

    public function amount(): float
    {
        $hospitalId = $this->hospitalId();

        if ($hospitalId === null) {
            return 0;
        }

        $stored = $this->settings->get(self::GROUP, self::KEY, null, $hospitalId);

        if (is_array($stored)) {
            return max(0, (float) ($stored['amount'] ?? 0));
        }

        return max(0, (float) $stored);
    }

    public function save(float $amount): float
    {
        $hospitalId = $this->hospitalId();
        $amount = max(0, round($amount, 2));

        if ($hospitalId === null) {
            return $amount;
        }

        $this->settings->set(self::GROUP, self::KEY, ['amount' => $amount], $hospitalId, 'array');

        return $amount;
    }

    private function hospitalId(): ?int
    {
        $fromUser = $this->currentUser->hospitalId();

        if ($fromUser !== null) {
            return $fromUser;
        }

        $code = (string) config('hms.default_hospital_code', 'MAIN');
        $id = HospitalModel::query()->where('code', $code)->value('id')
            ?? HospitalModel::query()->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }
}
