<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Core\Support\Contracts\StockBatch;
use App\Modules\Inventory\Application\Actions\AdjustStockAction;
use App\Modules\Inventory\Application\Actions\IssueStockAction;
use App\Modules\Inventory\Application\Actions\ReceiveStockAction;
use App\Modules\Inventory\Application\Actions\TransferStockAction;
use App\Modules\Inventory\Application\Queries\StockAlertsQuery;
use App\Modules\Inventory\Domain\Enums\MovementType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockLevelModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Inventory\Presentation\Requests\AdjustStockRequest;
use App\Modules\Inventory\Presentation\Requests\IssueStockRequest;
use App\Modules\Inventory\Presentation\Requests\ReceiveStockRequest;
use App\Modules\Inventory\Presentation\Requests\TransferStockRequest;
use App\Modules\Inventory\Presentation\Resources\InventoryBatchResource;
use App\Modules\Inventory\Presentation\Resources\StockMovementResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StockController extends ApiController
{
    public function levels(Request $request): JsonResponse
    {
        $levels = StockLevelModel::query()
            ->with(['item', 'warehouse'])
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->boolean('pharmacy_only'), fn ($query) => $query->whereHas(
                'warehouse',
                fn ($warehouse) => $warehouse->where('is_pharmacy', true),
            ))
            ->when($request->filled('inventory_item_id'), fn ($query) => $query->where('inventory_item_id', $request->integer('inventory_item_id')))
            ->when($request->boolean('in_stock_only'), fn ($query) => $query->where('quantity', '>', 0))
            ->paginate($this->perPage(100));

        return ApiResponse::data(
            $levels->getCollection()->map(fn (StockLevelModel $level) => [
                'inventory_item_id' => $level->inventory_item_id,
                'item' => $level->item?->name,
                'sku' => $level->item?->sku,
                'unit' => $level->item?->unit,
                'warehouse_id' => $level->warehouse_id,
                'warehouse' => $level->warehouse?->name,
                'quantity' => $level->quantity,
                'available' => $level->availableQuantity(),
                'minimum_stock' => $level->item?->minimum_stock,
                'below_minimum' => (float) $level->quantity <= (float) ($level->item?->minimum_stock ?? 0),
            ])->all(),
            [
                'current_page' => $levels->currentPage(),
                'per_page' => $levels->perPage(),
                'total' => $levels->total(),
                'last_page' => $levels->lastPage(),
            ],
        );
    }

    public function movements(Request $request): JsonResponse
    {
        $movements = StockMovementModel::query()
            ->with(['item', 'warehouse', 'batch', 'performedBy'])
            ->when($request->filled('inventory_item_id'), fn ($query) => $query->where('inventory_item_id', $request->integer('inventory_item_id')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('type'), fn ($query) => $query->whereIn('type', (array) $request->input('type')))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $request->date('to')))
            ->orderByDesc('id')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(StockMovementResource::collection($movements), $movements);
    }

    public function alerts(Request $request, StockAlertsQuery $query): JsonResponse
    {
        return ApiResponse::data($query->execute(
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null,
            $request->filled('expiry_days') ? $request->integer('expiry_days') : null,
        ));
    }

    public function receive(ReceiveStockRequest $request, ReceiveStockAction $action): JsonResponse
    {
        return ApiResponse::created(new InventoryBatchResource($action->execute($request->validated())));
    }

    public function issue(IssueStockRequest $request, IssueStockAction $action): JsonResponse
    {
        $drawn = $action->execute(
            inventoryItemId: $request->integer('inventory_item_id'),
            warehouseId: $request->integer('warehouse_id'),
            quantity: (float) $request->input('quantity'),
            type: MovementType::Sale,
            referenceType: $request->filled('department_id') ? 'department_issue' : null,
            referenceId: $request->filled('department_id') ? $request->integer('department_id') : null,
            batchId: $request->filled('batch_id') ? $request->integer('batch_id') : null,
            notes: $request->input('notes'),
        );

        return ApiResponse::data([
            'batches' => array_map(static fn (StockBatch $batch) => $batch->toArray(), $drawn),
        ]);
    }

    public function transfer(TransferStockRequest $request, TransferStockAction $action): JsonResponse
    {
        $drawn = $action->execute($request->validated());

        return ApiResponse::data([
            'batches' => array_map(static fn (StockBatch $batch) => $batch->toArray(), $drawn),
        ]);
    }

    public function adjust(AdjustStockRequest $request, AdjustStockAction $action): JsonResponse
    {
        return ApiResponse::created(new StockMovementResource(
            $action->execute($request->validated())->load(['item', 'warehouse', 'batch'])
        ));
    }

    public function writeOffExpired(Request $request, AdjustStockAction $action): JsonResponse
    {
        $written = $action->writeOffExpired(
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null,
        );

        return ApiResponse::data([
            'written_off' => count($written),
            'movements' => StockMovementResource::collection($written)->resolve(),
        ]);
    }
}
