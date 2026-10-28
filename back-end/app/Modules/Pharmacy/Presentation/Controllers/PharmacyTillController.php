<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacyReturnAction;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacySaleAction;
use App\Modules\Pharmacy\Application\Actions\PharmacyCashSessionAction;
use App\Modules\Pharmacy\Application\Actions\PharmacyDebtAction;
use App\Modules\Pharmacy\Application\Actions\PharmacyPurchaseAction;
use App\Modules\Pharmacy\Application\Actions\PharmacyStocktakeAction;
use App\Modules\Pharmacy\Presentation\Requests\CloseCashSessionRequest;
use App\Modules\Pharmacy\Presentation\Requests\CountStocktakeLineRequest;
use App\Modules\Pharmacy\Presentation\Requests\OpenCashSessionRequest;
use App\Modules\Pharmacy\Presentation\Requests\ManagePosShelfRequest;
use App\Modules\Pharmacy\Presentation\Requests\StorePharmacyDebtPaymentRequest;
use App\Modules\Pharmacy\Presentation\Requests\StorePharmacyPurchaseRequest;
use App\Modules\Pharmacy\Presentation\Requests\StorePharmacyReturnRequest;
use App\Modules\Pharmacy\Presentation\Requests\StoreSupplierPaymentRequest;
use App\Modules\Pharmacy\Presentation\Resources\PharmacyReturnResource;
use App\Modules\Pharmacy\Presentation\Resources\PharmacyStocktakeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PharmacyTillController extends ApiController
{
    public function storeReturn(StorePharmacyReturnRequest $request, CreatePharmacyReturnAction $returns): JsonResponse
    {
        $document = $returns->execute(
            (int) $request->validated('sale_id'),
            $request->validated('lines'),
            $request->validated('reason'),
        );

        return ApiResponse::created(new PharmacyReturnResource($document));
    }

    public function customers(Request $request, PharmacyDebtAction $debts): JsonResponse
    {
        return ApiResponse::data($debts->search($request->string('search')->toString()));
    }

    public function debts(Request $request, PharmacyDebtAction $debts): JsonResponse
    {
        return ApiResponse::data($debts->accounts(
            $request->string('search')->toString(),
            $request->string('period')->toString(),
            $request->string('date')->toString(),
        ));
    }

    public function collect(StorePharmacyDebtPaymentRequest $request, PharmacyDebtAction $debts): JsonResponse
    {
        $debts->collect(
            (int) $request->validated('customer_id'),
            (float) $request->validated('amount'),
            $request->filled('sale_id') ? (int) $request->validated('sale_id') : null,
            $request->validated('note'),
        );

        return ApiResponse::message('تم التحصيل');
    }

    public function purchases(Request $request, PharmacyPurchaseAction $purchases): JsonResponse
    {
        return ApiResponse::data($purchases->list(
            $request->string('search')->toString(),
            $request->string('period')->toString(),
            $request->string('date')->toString(),
        ));
    }

    public function storePurchase(StorePharmacyPurchaseRequest $request, PharmacyPurchaseAction $purchases): JsonResponse
    {
        $payload = $request->validated();
        $purchases->store(
            (string) ($payload['supplier_name'] ?? ''),
            $payload['supplier_phone'] ?? null,
            isset($payload['supplier_id']) ? (int) $payload['supplier_id'] : null,
            (string) $payload['invoice_number'],
            (float) $payload['total'],
            (float) ($payload['cash_amount'] ?? 0),
            $payload['received_at'] ?? null,
        );

        return ApiResponse::message('حُفظ المصروف', 201);
    }

    public function payPurchase(StoreSupplierPaymentRequest $request, PharmacyPurchaseAction $purchases): JsonResponse
    {
        $purchases->pay((int) $request->validated('purchase_id'), (float) $request->validated('amount'));

        return ApiResponse::message('سُجّلت الدفعة');
    }

    public function currentStocktake(PharmacyStocktakeAction $stocktakes): JsonResponse
    {
        $stocktake = $stocktakes->current();

        return ApiResponse::data($stocktake === null ? null : (new PharmacyStocktakeResource($stocktake))->resolve());
    }

    public function openStocktake(ManagePosShelfRequest $request, PharmacyStocktakeAction $stocktakes): JsonResponse
    {
        return ApiResponse::data((new PharmacyStocktakeResource($stocktakes->open()))->resolve());
    }

    public function countLine(
        CountStocktakeLineRequest $request,
        int $stocktake,
        int $line,
        PharmacyStocktakeAction $stocktakes,
    ): JsonResponse {
        $saved = $stocktakes->count($stocktake, $line, (float) $request->validated('counted_quantity'));

        return ApiResponse::data([
            'id' => $saved->id,
            'system_quantity' => $saved->system_quantity === null ? null : (float) $saved->system_quantity,
            'counted_quantity' => $saved->counted_quantity === null ? null : (float) $saved->counted_quantity,
        ]);
    }

    public function postStocktake(ManagePosShelfRequest $request, int $stocktake, PharmacyStocktakeAction $stocktakes): JsonResponse
    {
        return ApiResponse::data((new PharmacyStocktakeResource($stocktakes->post($stocktake)))->resolve());
    }

    public function cashSession(CreatePharmacySaleAction $sales, PharmacyCashSessionAction $cash): JsonResponse
    {
        return ApiResponse::data($cash->current($sales->hospitalId()));
    }

    public function openCash(OpenCashSessionRequest $request, CreatePharmacySaleAction $sales, PharmacyCashSessionAction $cash): JsonResponse
    {
        $session = $cash->openSession($sales->hospitalId(), (float) $request->validated('opening_float'));

        return ApiResponse::data($cash->current((int) $session->hospital_id), [], 201);
    }

    public function closeCash(CloseCashSessionRequest $request, CreatePharmacySaleAction $sales, PharmacyCashSessionAction $cash): JsonResponse
    {
        $cash->close(
            $sales->hospitalId(),
            (float) $request->validated('counted_cash'),
            $request->validated('note'),
        );

        return ApiResponse::data($cash->current($sales->hospitalId()));
    }
}
