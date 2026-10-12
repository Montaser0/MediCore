<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabTestModel;
use App\Modules\Laboratory\Presentation\Requests\StoreLabTestRequest;
use App\Modules\Laboratory\Presentation\Resources\LabTestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The test catalogue the doctor orders from and the laboratory maintains.
 */
final class LabTestController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $tests = LabTestModel::query()
            ->with('parameters')
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->search($request->string('search')->toString())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->orderBy('name')
            ->paginate($this->perPage(100));

        return ApiResponse::paginated(LabTestResource::collection($tests), $tests);
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::data(
            LabTestModel::query()
                ->active()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->all()
        );
    }

    public function show(LabTestModel $labTest): JsonResponse
    {
        return ApiResponse::resource(new LabTestResource($labTest->load('parameters')));
    }

    public function store(StoreLabTestRequest $request): JsonResponse
    {
        $test = DB::transaction(function () use ($request): LabTestModel {
            $test = LabTestModel::query()->create($this->attributes($request, true));

            $this->syncParameters($test, $request->input('parameters', []));

            return $test;
        });

        return ApiResponse::created(new LabTestResource($test->load('parameters')));
    }

    public function update(StoreLabTestRequest $request, LabTestModel $labTest): JsonResponse
    {
        DB::transaction(function () use ($request, $labTest): void {
            $labTest->fill($this->attributes($request, false))->save();

            if ($request->has('parameters')) {
                $labTest->parameters()->delete();
                $this->syncParameters($labTest, $request->input('parameters', []));
            }
        });

        return ApiResponse::resource(new LabTestResource($labTest->fresh('parameters')));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreLabTestRequest $request, bool $creating): array
    {
        $attributes = $request->safe()->except('parameters');

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
        $number = ((int) LabTestModel::query()->max('id')) + 1;

        do {
            $code = 'T-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while (LabTestModel::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @param  array<int, array<string, mixed>>  $parameters
     */
    private function syncParameters(LabTestModel $test, array $parameters): void
    {
        foreach (array_values($parameters) as $index => $parameter) {
            $test->parameters()->create($parameter + ['display_order' => $index]);
        }
    }

    public function destroy(Request $request, LabTestModel $labTest): JsonResponse
    {
        DeactivateRecord::run($request, $labTest, Permissions::LABORATORY_TESTS_MANAGE);

        return ApiResponse::noContent();
    }
}
