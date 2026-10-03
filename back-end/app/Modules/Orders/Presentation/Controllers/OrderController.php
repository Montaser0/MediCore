<?php

declare(strict_types=1);

namespace App\Modules\Orders\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Orders\Application\Actions\CancelOrderAction;
use App\Modules\Orders\Application\Actions\ChangeOrderStatusAction;
use App\Modules\Orders\Application\Actions\CreateOrderAction;
use App\Modules\Orders\Application\Queries\ListOrdersQuery;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use App\Modules\Orders\Presentation\Requests\CancelOrderRequest;
use App\Modules\Orders\Presentation\Requests\StoreOrderRequest;
use App\Modules\Orders\Presentation\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderController extends ApiController
{
    public function index(Request $request, ListOrdersQuery $query): JsonResponse
    {
        $orders = $query->execute(
            array_filter($request->only([
                'encounter_id', 'patient_id', 'type', 'status', 'priority',
                'department_id', 'ordered_by', 'date', 'open_only', 'worklist',
            ])),
            $this->perPage(),
        );

        return ApiResponse::paginated(OrderResource::collection($orders), $orders);
    }

    public function show(OrderModel $order): JsonResponse
    {
        return ApiResponse::resource(new OrderResource(
            $order->load(['items', 'department', 'orderedBy', 'encounter.patient'])
        ));
    }

    public function forEncounter(EncounterModel $encounter, ListOrdersQuery $query): JsonResponse
    {
        $orders = $query->execute(['encounter_id' => $encounter->getKey()], $this->perPage(100));

        return ApiResponse::paginated(OrderResource::collection($orders), $orders);
    }

    public function store(StoreOrderRequest $request, CreateOrderAction $action): JsonResponse
    {
        return ApiResponse::created(new OrderResource($action->execute($request->validated())));
    }

    /**
     * The receiving department accepts the order into its worklist.
     */
    public function accept(OrderModel $order, ChangeOrderStatusAction $action): JsonResponse
    {
        return ApiResponse::resource(new OrderResource(
            $action->execute($order, OrderStatus::Accepted)->load('items')
        ));
    }

    public function cancel(CancelOrderRequest $request, OrderModel $order, CancelOrderAction $action): JsonResponse
    {
        return ApiResponse::resource(new OrderResource(
            $action->execute($order, $request->string('reason')->toString())->load('items')
        ));
    }
}
