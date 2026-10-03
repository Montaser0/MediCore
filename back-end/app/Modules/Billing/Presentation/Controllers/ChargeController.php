<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Billing\Application\Actions\CreateChargeAction;
use App\Modules\Billing\Infrastructure\Persistence\Models\ChargeModel;
use App\Modules\Billing\Presentation\Requests\StoreChargeRequest;
use App\Modules\Billing\Presentation\Resources\ChargeResource;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChargeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $charges = ChargeModel::query()
            ->with(['service', 'patient'])
            ->forCurrentHospital()
            ->when($request->filled('patient_id'), fn ($query) => $query->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('encounter_id'), fn ($query) => $query->where('encounter_id', $request->integer('encounter_id')))
            ->when($request->boolean('uninvoiced_only'), fn ($query) => $query->uninvoiced())
            ->when($request->filled('date'), fn ($query) => $query->whereDate('created_at', $request->date('date')))
            ->orderByDesc('created_at')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(ChargeResource::collection($charges), $charges);
    }

    public function forEncounter(EncounterModel $encounter): JsonResponse
    {
        $charges = ChargeModel::query()
            ->with('service')
            ->where('encounter_id', $encounter->getKey())
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::data([
            'charges' => ChargeResource::collection($charges)->resolve(),
            'total' => round((float) $charges->sum('total'), 2),
            'uninvoiced_total' => round((float) $charges->whereNull('invoice_id')->sum('total'), 2),
        ]);
    }

    public function store(StoreChargeRequest $request, CreateChargeAction $action): JsonResponse
    {
        return ApiResponse::created(new ChargeResource(
            $action->execute($request->validated())->load('service')
        ));
    }
}
