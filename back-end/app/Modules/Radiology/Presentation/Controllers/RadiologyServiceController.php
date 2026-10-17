<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Radiology\Application\Services\RadiologyInjectionPrice;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyServiceModel;
use App\Modules\Radiology\Presentation\Requests\StoreRadiologyServiceRequest;
use App\Modules\Radiology\Presentation\Resources\RadiologyServiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RadiologyServiceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $services = RadiologyServiceModel::query()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->search($request->string('search')->toString())
            ->when($request->filled('modality'), fn ($query) => $query->where('modality', $request->string('modality')))
            ->orderBy('name')
            ->paginate($this->perPage(100));

        return ApiResponse::paginated(RadiologyServiceResource::collection($services), $services);
    }

    public function modalities(): JsonResponse
    {
        return ApiResponse::data(
            RadiologyServiceModel::query()
                ->active()
                ->whereNotNull('modality')
                ->distinct()
                ->orderBy('modality')
                ->pluck('modality')
                ->all()
        );
    }

    public function injectionPrice(RadiologyInjectionPrice $prices): JsonResponse
    {
        return ApiResponse::data(['price' => $prices->amount()]);
    }

    public function updateInjectionPrice(Request $request, RadiologyInjectionPrice $prices): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::RADIOLOGY_SERVICES_MANAGE) === true, 403);

        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        return ApiResponse::data([
            'price' => $prices->save((float) $validated['price']),
        ]);
    }

    public function store(StoreRadiologyServiceRequest $request): JsonResponse
    {
        return ApiResponse::created(new RadiologyServiceResource(
            RadiologyServiceModel::query()->create($this->attributes($request, true))
        ));
    }

    public function update(StoreRadiologyServiceRequest $request, RadiologyServiceModel $radiologyService): JsonResponse
    {
        $radiologyService->fill($this->attributes($request, false))->save();

        return ApiResponse::resource(new RadiologyServiceResource($radiologyService));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreRadiologyServiceRequest $request, bool $creating): array
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
        $number = ((int) RadiologyServiceModel::query()->max('id')) + 1;

        do {
            $code = 'R-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while (RadiologyServiceModel::query()->where('code', $code)->exists());

        return $code;
    }

    public function destroy(Request $request, RadiologyServiceModel $radiologyService): JsonResponse
    {
        DeactivateRecord::run($request, $radiologyService, Permissions::RADIOLOGY_SERVICES_MANAGE);

        return ApiResponse::noContent();
    }
}
