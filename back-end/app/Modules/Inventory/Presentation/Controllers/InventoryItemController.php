<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Inventory\Application\Support\ItemBarcode;
use App\Modules\Inventory\Domain\Enums\ItemType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use App\Modules\Inventory\Presentation\Requests\GenerateItemBarcodesRequest;
use App\Modules\Inventory\Presentation\Requests\StoreInventoryItemRequest;
use App\Modules\Inventory\Presentation\Resources\InventoryBatchResource;
use App\Modules\Inventory\Presentation\Resources\InventoryItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InventoryItemController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $items = InventoryItemModel::query()
            ->with('stockLevels.warehouse')
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->search($request->string('search')->toString())
            ->when($request->filled('item_type'), fn ($query) => $query->where('item_type', $request->string('item_type')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->orderBy('name')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(InventoryItemResource::collection($items), $items);
    }

    public function types(): JsonResponse
    {
        return ApiResponse::data(array_map(
            static fn (ItemType $type) => ['value' => $type->value, 'label' => $type->label()],
            ItemType::cases(),
        ));
    }

    public function generateBarcodes(GenerateItemBarcodesRequest $request): JsonResponse
    {
        return ApiResponse::data(['generated' => ItemBarcode::fillMissing()]);
    }

    public function nextBarcode(Request $request): JsonResponse
    {
        $kind = $request->string('kind')->toString();

        if (! in_array($kind, ['pharmacy', 'general'], true)) {
            return ApiResponse::message('حدد نوع المستودع: صيدلية أو عام.', 422);
        }

        return ApiResponse::data(['barcode' => ItemBarcode::next($kind)]);
    }

    public function byBarcode(string $barcode): JsonResponse
    {
        $item = InventoryItemModel::query()->where('barcode', $barcode)->first();

        if ($item === null) {
            return ApiResponse::message('لا يوجد صنف بهذا الباركود.', 404);
        }

        return ApiResponse::resource(new InventoryItemResource($item));
    }

    public function show(InventoryItemModel $item): JsonResponse
    {
        return ApiResponse::resource(new InventoryItemResource($item->load('stockLevels.warehouse')));
    }

    public function batches(Request $request, InventoryItemModel $item): JsonResponse
    {
        $batches = $item->batches()
            ->with(['warehouse', 'item'])
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->boolean('available_only'), fn ($query) => $query->available()->notExpired())
            ->fefo()
            ->get();

        return ApiResponse::data(InventoryBatchResource::collection($batches)->resolve());
    }

    public function store(StoreInventoryItemRequest $request): JsonResponse
    {
        return ApiResponse::created(new InventoryItemResource(
            InventoryItemModel::query()->create($request->validated())
        ));
    }

    public function update(StoreInventoryItemRequest $request, InventoryItemModel $item): JsonResponse
    {
        $attributes = $request->validated();

        if (array_key_exists('barcode', $attributes) && blank($attributes['barcode'])) {
            unset($attributes['barcode']);
        }

        $item->fill($attributes)->save();

        return ApiResponse::resource(new InventoryItemResource($item->load('stockLevels.warehouse')));
    }

    public function destroy(Request $request, InventoryItemModel $item): JsonResponse
    {
        DeactivateRecord::run($request, $item, Permissions::INVENTORY_ITEMS_MANAGE);

        return ApiResponse::noContent();
    }
}
