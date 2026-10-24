<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodProductModel;
use App\Modules\Laboratory\Presentation\Requests\StoreBloodProductRequest;
use App\Modules\Laboratory\Presentation\Resources\BloodProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BloodProductController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        BloodProductModel::ensureCatalog();

        $products = BloodProductModel::query()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->search($request->string('search')->toString())
            ->orderBy('name')
            ->paginate($this->perPage(100));

        return ApiResponse::paginated(BloodProductResource::collection($products), $products);
    }

    public function store(StoreBloodProductRequest $request): JsonResponse
    {
        $product = BloodProductModel::storeCatalog($this->attributes($request, true));

        return ApiResponse::created(new BloodProductResource($product->fresh()));
    }

    public function update(StoreBloodProductRequest $request, BloodProductModel $bloodProduct): JsonResponse
    {
        $bloodProduct->fill($this->attributes($request, false))->save();

        return ApiResponse::resource(new BloodProductResource($bloodProduct->fresh()));
    }

    public function destroy(Request $request, BloodProductModel $bloodProduct): JsonResponse
    {
        DeactivateRecord::run($request, $bloodProduct, Permissions::LABORATORY_TESTS_MANAGE);

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreBloodProductRequest $request, bool $creating): array
    {
        $attributes = $request->validated();

        if (blank($attributes['code'] ?? null)) {
            if ($creating) {
                $attributes['code'] = $this->nextCode();
            } else {
                unset($attributes['code']);
            }
        }

        return $attributes;
    }

    private function nextCode(): string
    {
        $number = ((int) BloodProductModel::query()->max('id')) + 1;

        do {
            $code = 'BLD-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while (BloodProductModel::query()->where('code', $code)->exists());

        return $code;
    }
}
