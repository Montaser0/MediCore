<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacySaleAction;
use App\Modules\Pharmacy\Application\Actions\ProvisionMedicationStockAction;
use App\Modules\Pharmacy\Application\Actions\ReceiveMedicationStockAction;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Pharmacy\Presentation\Requests\ProvisionMedicationStockRequest;
use App\Modules\Pharmacy\Presentation\Requests\ReceiveMedicationStockRequest;
use App\Modules\Pharmacy\Presentation\Resources\MedicationResource;
use Illuminate\Http\JsonResponse;

final class MedicationStockController extends ApiController
{
    public function provision(
        ProvisionMedicationStockRequest $request,
        MedicationModel $medication,
        ProvisionMedicationStockAction $action,
        CreatePharmacySaleAction $sales,
    ): JsonResponse {
        $payload = $request->validated();
        $action->execute(
            $medication,
            $sales->hospitalId(),
            $payload['unit'] ?? null,
            $payload['barcode'] ?? null,
            isset($payload['warehouse_id']) ? (int) $payload['warehouse_id'] : null,
        );

        return ApiResponse::resource(new MedicationResource($medication->refresh()->load('inventoryItem')));
    }

    public function receive(
        ReceiveMedicationStockRequest $request,
        MedicationModel $medication,
        ReceiveMedicationStockAction $action,
        CreatePharmacySaleAction $sales,
    ): JsonResponse {
        $action->execute($medication, $sales->hospitalId(), $request->validated());

        return ApiResponse::resource(new MedicationResource($medication->refresh()->load('inventoryItem')));
    }
}
