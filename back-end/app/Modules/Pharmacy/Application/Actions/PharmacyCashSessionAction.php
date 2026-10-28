<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyCashSessionModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyDebtPaymentModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyPurchaseModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyReturnModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySupplierPaymentModel;
use Illuminate\Support\Carbon;

final class PharmacyCashSessionAction
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    /**
     * @return array<string, mixed>
     */
    public function current(int $hospitalId): array
    {
        $open = $this->open($hospitalId);

        return [
            'open' => $open === null ? null : $this->present($open, true),
            'recent' => PharmacyCashSessionModel::query()
                ->where('hospital_id', $hospitalId)
                ->whereNotNull('closed_at')
                ->latest('closed_at')
                ->limit(15)
                ->get()
                ->map(fn (PharmacyCashSessionModel $session) => $this->present($session, false))
                ->all(),
        ];
    }

    public function openSession(int $hospitalId, float $openingFloat): PharmacyCashSessionModel
    {
        if ($this->open($hospitalId) !== null) {
            throw BusinessRuleException::make('الصندوق مفتوح. أغلقه قبل فتح صندوق جديد');
        }

        return PharmacyCashSessionModel::query()->create([
            'hospital_id' => $hospitalId,
            'user_id' => $this->currentUser->id(),
            'opened_at' => now(),
            'opening_float' => round(max($openingFloat, 0), 2),
        ]);
    }

    public function close(int $hospitalId, float $counted, ?string $note): PharmacyCashSessionModel
    {
        $session = $this->open($hospitalId);
        if ($session === null) {
            throw BusinessRuleException::make('لا يوجد صندوق مفتوح');
        }

        $session->closed_at = now();
        $figures = $this->figures($session, $session->closed_at);
        $counted = round(max($counted, 0), 2);
        $session->counted_cash = $counted;
        $session->expected_cash = $figures['expected'];
        $session->variance = round($counted - $figures['expected'], 2);
        $session->note = trim((string) $note) !== '' ? trim((string) $note) : null;
        $session->save();

        return $session;
    }

    private function open(int $hospitalId): ?PharmacyCashSessionModel
    {
        return PharmacyCashSessionModel::query()
            ->where('hospital_id', $hospitalId)
            ->open()
            ->latest('opened_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PharmacyCashSessionModel $session, bool $live): array
    {
        $figures = $live
            ? $this->figures($session, now())
            : [
                'opening_float' => (float) $session->opening_float,
                'cash_sales' => null,
                'debt_collections' => null,
                'cash_returns' => null,
                'expenses' => null,
                'expected' => (float) $session->expected_cash,
            ];

        return [
            'id' => $session->id,
            'opened_at' => $session->opened_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'opening_float' => $figures['opening_float'],
            'cash_sales' => $figures['cash_sales'],
            'debt_collections' => $figures['debt_collections'],
            'cash_returns' => $figures['cash_returns'],
            'expenses' => $figures['expenses'],
            'expected' => $figures['expected'],
            'counted_cash' => $session->counted_cash,
            'variance' => $session->variance,
            'note' => $session->note,
        ];
    }

    /**
     * @return array{opening_float: float, cash_sales: float, debt_collections: float, cash_returns: float, expenses: float, expected: float}
     */
    private function figures(PharmacyCashSessionModel $session, Carbon $until): array
    {
        $from = $session->opened_at ?? $until;
        $hospitalId = (int) $session->hospital_id;

        $cashSales = round((float) PharmacySaleModel::query()
            ->where('hospital_id', $hospitalId)
            ->whereBetween('sold_at', [$from, $until])
            ->sum('cash_amount'), 2);
        $collections = round((float) PharmacyDebtPaymentModel::query()
            ->where('hospital_id', $hospitalId)
            ->whereBetween('paid_at', [$from, $until])
            ->sum('amount'), 2);
        $returns = round((float) PharmacyReturnModel::query()
            ->where('hospital_id', $hospitalId)
            ->whereBetween('returned_at', [$from, $until])
            ->sum('cash_refund'), 2);
        $expenses = round(
            (float) PharmacyPurchaseModel::query()
                ->where('hospital_id', $hospitalId)
                ->whereBetween('received_at', [$from, $until])
                ->sum('cash_amount')
            + (float) PharmacySupplierPaymentModel::query()
                ->where('hospital_id', $hospitalId)
                ->whereBetween('paid_at', [$from, $until])
                ->sum('amount'),
            2,
        );
        $opening = round((float) $session->opening_float, 2);

        return [
            'opening_float' => $opening,
            'cash_sales' => $cashSales,
            'debt_collections' => $collections,
            'cash_returns' => $returns,
            'expenses' => $expenses,
            'expected' => round($opening + $cashSales + $collections - $returns - $expenses, 2),
        ];
    }
};
