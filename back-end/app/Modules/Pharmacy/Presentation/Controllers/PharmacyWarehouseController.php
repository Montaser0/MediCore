<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Inventory\Presentation\Resources\WarehouseResource;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacySaleAction;
use Illuminate\Http\JsonResponse;

/**
 * Pharmacy warehouses for both editions. The inventory module's own routes
 * stay off in the standalone pharmacy, so the shelf screen asks here.
 */
final class PharmacyWarehouseController extends ApiController
{
    public function index(CreatePharmacySaleAction $sales): JsonResponse
    {
        $warehouses = WarehouseModel::query()
            ->where('hospital_id', $sales->hospitalId())
            ->pharmacy()
            ->active()
            ->orderBy('name')
            ->get();

        return ApiResponse::data(WarehouseResource::collection($warehouses)->resolve());
    }

    public function store(CreatePharmacySaleAction $sales): JsonResponse
    {
        $hospitalId = $sales->hospitalId();
        $existing = WarehouseModel::query()
            ->where('hospital_id', $hospitalId)
            ->pharmacy()
            ->active()
            ->first();

        if ($existing instanceof WarehouseModel) {
            return ApiResponse::resource(new WarehouseResource($existing));
        }

        $warehouse = WarehouseModel::query()->create([
            'hospital_id' => $hospitalId,
            'name' => 'صيدلية',
            'code' => 'PH-'.$hospitalId,
            'is_pharmacy' => true,
            'is_active' => true,
        ]);

        return ApiResponse::created(new WarehouseResource($warehouse));
    }
}
