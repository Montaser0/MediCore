<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Support;

use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetModel;

final class AssetCode
{
    public static function next(int $hospitalId): string
    {
        $max = 0;

        foreach (ItAssetModel::query()->where('hospital_id', $hospitalId)->pluck('asset_code') as $code) {
            if (preg_match('/^IT-(\d+)$/', (string) $code, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'IT-'.str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }
}
