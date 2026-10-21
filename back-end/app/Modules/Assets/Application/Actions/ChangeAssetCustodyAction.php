<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Actions;

use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Exceptions\InvalidAssetTransitionException;
use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetMovementModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use Illuminate\Support\Facades\DB;

final class ChangeAssetCustodyAction
{
    public function assign(
        ItAssetModel $asset,
        ?string $holder,
        ?int $departmentId,
        ?string $location,
        ?string $note,
        ?int $actorId,
    ): ItAssetModel {
        return DB::transaction(function () use ($asset, $holder, $departmentId, $location, $note, $actorId): ItAssetModel {
            $asset = $this->lock($asset);
            $this->assertStatus($asset, [AssetStatus::InStock, AssetStatus::Assigned]);
            $this->assertDepartment($asset, $departmentId);

            $type = $asset->status === AssetStatus::Assigned
                ? AssetMovementType::Transferred
                : AssetMovementType::Assigned;

            $this->move($asset, $type, [
                'status' => AssetStatus::Assigned,
                'received_by' => filled($holder) ? trim($holder) : null,
                'department_id' => $departmentId ?? $asset->department_id,
                'location' => $location ?? $asset->location,
            ], $note, $actorId);

            return $asset->load('department');
        });
    }

    public function returnToStock(
        ItAssetModel $asset,
        ?int $departmentId,
        ?string $location,
        ?string $note,
        ?int $actorId,
    ): ItAssetModel {
        return DB::transaction(function () use ($asset, $departmentId, $location, $note, $actorId): ItAssetModel {
            $asset = $this->lock($asset);
            $this->assertStatus($asset, [AssetStatus::Assigned]);
            $this->assertDepartment($asset, $departmentId);

            $this->move($asset, AssetMovementType::Returned, [
                'status' => AssetStatus::InStock,
                'received_by' => null,
                'department_id' => $departmentId ?? $asset->department_id,
                'location' => $location ?? $asset->location,
            ], $note, $actorId);

            return $asset->load('department');
        });
    }

    public function sendToMaintenance(ItAssetModel $asset, ?string $note, ?int $actorId): ItAssetModel
    {
        return DB::transaction(function () use ($asset, $note, $actorId): ItAssetModel {
            $asset = $this->lock($asset);
            $this->assertStatus($asset, [AssetStatus::InStock, AssetStatus::Assigned]);

            $this->move($asset, AssetMovementType::Maintenance, [
                'status' => AssetStatus::Maintenance,
            ], $note, $actorId);

            return $asset->load('department');
        });
    }

    public function markRepaired(ItAssetModel $asset, ?string $note, ?int $actorId): ItAssetModel
    {
        return DB::transaction(function () use ($asset, $note, $actorId): ItAssetModel {
            $asset = $this->lock($asset);
            $this->assertStatus($asset, [AssetStatus::Maintenance]);

            $this->move($asset, AssetMovementType::Repaired, [
                'status' => filled($asset->received_by) ? AssetStatus::Assigned : AssetStatus::InStock,
            ], $note, $actorId);

            return $asset->load('department');
        });
    }

    public function retire(ItAssetModel $asset, ?string $note, ?int $actorId): ItAssetModel
    {
        return DB::transaction(function () use ($asset, $note, $actorId): ItAssetModel {
            $asset = $this->lock($asset);
            $this->assertStatus($asset, [AssetStatus::InStock, AssetStatus::Assigned, AssetStatus::Maintenance]);

            $this->move($asset, AssetMovementType::Retired, [
                'status' => AssetStatus::Retired,
                'received_by' => null,
            ], $note, $actorId);

            return $asset->load('department');
        });
    }

    private function lock(ItAssetModel $asset): ItAssetModel
    {
        $locked = ItAssetModel::query()->whereKey($asset->getKey())->lockForUpdate()->first();

        if ($locked === null) {
            throw InvalidAssetTransitionException::because('الجهاز غير موجود.');
        }

        return $locked;
    }

    /**
     * @param  array<int, AssetStatus>  $allowed
     */
    private function assertStatus(ItAssetModel $asset, array $allowed): void
    {
        if (! in_array($asset->status, $allowed, true)) {
            throw InvalidAssetTransitionException::forStatus($asset->status);
        }
    }

    private function assertDepartment(ItAssetModel $asset, ?int $departmentId): void
    {
        if ($departmentId === null) {
            return;
        }

        $exists = DepartmentModel::query()
            ->whereKey($departmentId)
            ->where('hospital_id', $asset->hospital_id)
            ->exists();

        if (! $exists) {
            throw InvalidAssetTransitionException::because('القسم لا يتبع هذا المشفى.');
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function move(
        ItAssetModel $asset,
        AssetMovementType $type,
        array $changes,
        ?string $note,
        ?int $actorId,
    ): void {
        $fromDepartment = $asset->department_id;
        $fromHolder = $asset->received_by;
        $asset->forceFill($changes)->save();

        ItAssetMovementModel::query()->create([
            'it_asset_id' => $asset->getKey(),
            'hospital_id' => $asset->hospital_id,
            'type' => $type,
            'from_department_id' => $fromDepartment,
            'to_department_id' => $asset->department_id,
            'from_holder' => $fromHolder,
            'to_holder' => $asset->received_by,
            'note' => $note,
            'actor_id' => $actorId,
            'occurred_at' => now(),
        ]);
    }
}
