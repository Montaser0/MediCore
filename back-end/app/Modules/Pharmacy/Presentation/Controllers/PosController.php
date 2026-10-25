<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacySaleAction;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleModel;
use App\Modules\Pharmacy\Presentation\Requests\SavePosMedicationRequest;
use App\Modules\Pharmacy\Presentation\Requests\StorePharmacySaleRequest;
use App\Modules\Pharmacy\Presentation\Resources\MedicationResource;
use App\Modules\Pharmacy\Presentation\Resources\PharmacySaleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PosController extends ApiController
{
    public function index(Request $request, CreatePharmacySaleAction $sales): JsonResponse
    {
        $medications = MedicationModel::query()
            ->with('inventoryItem')
            ->where('hospital_id', $sales->hospitalId())
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->search($request->string('search')->toString())
            ->orderBy('name')
            ->paginate($this->perPage(100));

        return ApiResponse::paginated(MedicationResource::collection($medications), $medications);
    }

    public function store(SavePosMedicationRequest $request, CreatePharmacySaleAction $sales): JsonResponse
    {
        $medication = MedicationModel::query()->create([
            ...$request->validated(),
            'hospital_id' => $sales->hospitalId(),
            'requires_prescription' => false,
            'is_active' => true,
        ]);

        return ApiResponse::created(new MedicationResource($medication));
    }

    public function update(SavePosMedicationRequest $request, MedicationModel $medication, CreatePharmacySaleAction $sales): JsonResponse
    {
        $this->guardShelf($medication, $sales->hospitalId());
        $attributes = $request->validated();

        if (blank($attributes['barcode'] ?? null)) {
            unset($attributes['barcode']);
        }

        $medication->fill($attributes)->save();

        return ApiResponse::resource(new MedicationResource($medication));
    }

    public function barcode(string $barcode, CreatePharmacySaleAction $sales): JsonResponse
    {
        $medication = MedicationModel::query()
            ->active()
            ->where('hospital_id', $sales->hospitalId())
            ->where('barcode', $barcode)
            ->first();

        if ($medication === null) {
            return ApiResponse::message('لا يوجد دواء بهذا الباركود', 404);
        }

        return ApiResponse::resource(new MedicationResource($medication->load('inventoryItem')));
    }

    public function sell(StorePharmacySaleRequest $request, CreatePharmacySaleAction $sales): JsonResponse
    {
        $payload = $request->validated();
        $sale = $sales->execute(
            $payload['lines'],
            (float) ($payload['discount'] ?? 0),
            array_key_exists('cash_amount', $payload) ? (float) $payload['cash_amount'] : null,
            isset($payload['customer_id']) ? (int) $payload['customer_id'] : null,
            $payload['customer_name'] ?? null,
            $payload['customer_phone'] ?? null,
        );

        return ApiResponse::created(new PharmacySaleResource($sale));
    }

    public function sales(Request $request, CreatePharmacySaleAction $sales): JsonResponse
    {
        $search = trim($request->string('search')->toString());
        $like = '%'.addcslashes($search, '%_\\').'%';

        $rows = PharmacySaleModel::query()
            ->where('hospital_id', $sales->hospitalId())
            ->with(['items.returnItems'])
            ->when($search === '', fn ($query) => $query->where('sold_at', '>=', now()->startOfDay()))
            ->when($search !== '', function ($query) use ($search, $like): void {
                $query->where('sold_at', '>=', now()->subDays(30)->startOfDay())
                    ->where(function ($inner) use ($search, $like): void {
                        $inner->where('sale_number', 'like', $like)
                            ->orWhereHas('items', function ($items) use ($search, $like): void {
                                $items->where('barcode', $search)->orWhere('name', 'like', $like);
                            });
                    });
            })
            ->latest('sold_at')
            ->limit(40)
            ->get();

        return ApiResponse::data(PharmacySaleResource::collection($rows)->resolve());
    }

    public function sale(string $saleNumber, CreatePharmacySaleAction $sales): JsonResponse
    {
        $sale = PharmacySaleModel::query()
            ->where('hospital_id', $sales->hospitalId())
            ->where('sale_number', $saleNumber)
            ->with(['items.returnItems'])
            ->first();

        if ($sale === null) {
            return ApiResponse::message('لا توجد فاتورة بهذا الرقم', 404);
        }

        return ApiResponse::resource(new PharmacySaleResource($sale));
    }

    private function guardShelf(MedicationModel $medication, int $hospitalId): void
    {
        if ((int) $medication->hospital_id !== $hospitalId) {
            abort(404);
        }
    }
}
