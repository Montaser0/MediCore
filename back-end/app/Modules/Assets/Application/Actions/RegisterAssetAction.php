<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Actions;

use App\Modules\Assets\Application\Support\AssetBarcode;
use App\Modules\Assets\Application\Support\AssetCode;
use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetMovementModel;
use Illuminate\Support\Facades\DB;

final class RegisterAssetAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, ?int $actorId): ItAssetModel
    {
        return DB::transaction(function () use ($attributes, $actorId): ItAssetModel {
            $hospitalId = (int) $attributes['hospital_id'];
            $code = filled($attributes['asset_code'] ?? null)
                ? (string) $attributes['asset_code']
                : AssetCode::next($hospitalId);

            $holder = filled($attributes['received_by'] ?? null) ? trim((string) $attributes['received_by']) : null;
            $attributes['asset_code'] = $code;
            $attributes['barcode'] = AssetBarcode::next($hospitalId);
            $attributes['received_by'] = $holder;
            $attributes['status'] = $holder === null ? AssetStatus::InStock : AssetStatus::Assigned;
            $attributes['is_active'] = true;

            $asset = ItAssetModel::query()->create($attributes);

            ItAssetMovementModel::query()->create([
                'it_asset_id' => $asset->getKey(),
                'hospital_id' => $hospitalId,
                'type' => AssetMovementType::Registered,
                'to_department_id' => $asset->department_id,
                'to_holder' => $holder,
                'note' => $asset->notes,
                'actor_id' => $actorId,
                'occurred_at' => now(),
            ]);

            return $asset->load('department');
        });
    }
}
