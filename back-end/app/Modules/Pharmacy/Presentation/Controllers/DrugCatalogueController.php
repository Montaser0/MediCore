<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Pharmacy\Application\Actions\CatalogueDrugAction;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacySaleAction;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\DrugCatalogueItemModel;
use App\Modules\Pharmacy\Presentation\Requests\AdoptCatalogueDrugRequest;
use App\Modules\Pharmacy\Presentation\Requests\StockCatalogueDrugRequest;
use App\Modules\Pharmacy\Presentation\Resources\DrugCatalogueResource;
use App\Modules\Pharmacy\Presentation\Resources\MedicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DrugCatalogueController extends ApiController
{
    public function index(Request $request, CreatePharmacySaleAction $sales): JsonResponse
    {
        $term = trim($request->string('search')->toString());
        if (mb_strlen($term) < 2) {
            return ApiResponse::data([], [
                'current_page' => 1,
                'per_page' => $this->perPage(20),
                'total' => 0,
                'last_page' => 1,
            ]);
        }

        $hospitalId = $this->hospitalId($sales);

        $items = DrugCatalogueItemModel::query()
            ->search($term, $request->boolean('name_only'))
            ->when(
                $request->filled('product_type'),
                fn ($query) => $query->where('product_type', $request->string('product_type')->toString()),
            )
            ->when(
                $hospitalId !== null,
                fn ($query) => $query->with(['shelf' => fn ($shelf) => $shelf->where('hospital_id', $hospitalId)]),
            )
            ->orderBy('name')
            ->paginate($this->perPage(20));

        return ApiResponse::paginated(DrugCatalogueResource::collection($items), $items);
    }

    public function stock(
        StockCatalogueDrugRequest $request,
        DrugCatalogueItemModel $catalogueItem,
        CatalogueDrugAction $catalogue,
        CreatePharmacySaleAction $sales,
    ): JsonResponse {
        $payload = $request->validated();
        $medication = $catalogue->stock(
            $catalogueItem,
            $sales->hospitalId(),
            (float) $payload['quantity'],
            (float) $payload['price'],
            $payload['barcode'] ?? null,
        );

        return ApiResponse::resource(new MedicationResource($medication));
    }

    public function adopt(
        AdoptCatalogueDrugRequest $request,
        DrugCatalogueItemModel $catalogueItem,
        CatalogueDrugAction $catalogue,
        CreatePharmacySaleAction $sales,
    ): JsonResponse {
        $price = $request->exists('price') && $request->input('price') !== null
            ? (float) $request->validated('price')
            : null;

        $medication = $catalogue->adopt($catalogueItem, $sales->hospitalId(), $price);

        return ApiResponse::resource(new MedicationResource($medication));
    }

    private function hospitalId(CreatePharmacySaleAction $sales): ?int
    {
        try {
            return $sales->hospitalId();
        } catch (BusinessRuleException) {
            return null;
        }
    }
}
