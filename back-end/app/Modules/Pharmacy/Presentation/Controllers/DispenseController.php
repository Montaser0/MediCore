<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Core\Support\Contracts\StockBatch;
use App\Core\Support\Contracts\StockService;
use App\Modules\Pharmacy\Application\Actions\DispenseMedicationAction;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationDispenseModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionItemModel;
use App\Modules\Pharmacy\Presentation\Requests\DispenseMedicationRequest;
use App\Modules\Pharmacy\Presentation\Resources\MedicationDispenseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DispenseController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $dispenses = MedicationDispenseModel::query()
            ->with(['medication', 'warehouse', 'dispensedBy', 'prescription'])
            ->forCurrentHospital()
            ->when($request->filled('prescription_id'), fn ($query) => $query->where('prescription_id', $request->integer('prescription_id')))
            ->when($request->filled('encounter_id'), fn ($query) => $query->where('encounter_id', $request->integer('encounter_id')))
            ->when($request->filled('patient_id'), fn ($query) => $query->whereHas(
                'encounter',
                fn ($encounter) => $encounter->where('patient_id', $request->integer('patient_id')),
            ))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('dispensed_at', $request->date('date')))
            ->orderByDesc('dispensed_at')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(MedicationDispenseResource::collection($dispenses), $dispenses);
    }

    /**
     * What the pharmacist sees before handing over: which batches are on the
     * shelf for this line, nearest expiry first.
     */
    public function availability(Request $request, PrescriptionItemModel $prescriptionItem, StockService $stock): JsonResponse
    {
        $prescriptionItem->loadMissing('medication');

        $warehouseId = $request->integer('warehouse_id');
        $inventoryItemId = $prescriptionItem->medication?->inventory_item_id;

        if ($inventoryItemId === null) {
            return ApiResponse::data([
                'is_stock_linked' => false,
                'available_quantity' => 0,
                'batches' => [],
            ]);
        }

        $batches = $warehouseId > 0
            ? $stock->issuableBatches((int) $inventoryItemId, $warehouseId)
            : [];

        return ApiResponse::data([
            'is_stock_linked' => true,
            'remaining_to_dispense' => $prescriptionItem->remainingQuantity(),
            'available_quantity' => $stock->availableQuantity((int) $inventoryItemId, $warehouseId > 0 ? $warehouseId : null),
            'batches' => array_map(static fn (StockBatch $batch) => $batch->toArray(), $batches),
        ]);
    }

    public function store(
        DispenseMedicationRequest $request,
        PrescriptionItemModel $prescriptionItem,
        DispenseMedicationAction $action,
    ): JsonResponse {
        return ApiResponse::created(new MedicationDispenseResource(
            $action->execute($prescriptionItem, $request->validated())
        ));
    }
}
