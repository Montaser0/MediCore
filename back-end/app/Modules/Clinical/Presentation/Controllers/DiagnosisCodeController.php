<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisCodeModel;
use App\Modules\Clinical\Presentation\Resources\DiagnosisCodeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lookup endpoint behind the diagnosis autocomplete in the clinic screen.
 */
final class DiagnosisCodeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $codes = DiagnosisCodeModel::query()
            ->active()
            ->search($request->string('search')->toString())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->orderBy('code')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(DiagnosisCodeResource::collection($codes), $codes);
    }
}
