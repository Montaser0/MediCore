<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyCustomerModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyDebtPaymentModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class PharmacyDebtAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly CreatePharmacySaleAction $sales,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(?string $term): array
    {
        $term = trim((string) $term);
        $hospitalId = $this->sales->hospitalId();

        return PharmacyCustomerModel::query()
            ->where('hospital_id', $hospitalId)
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)->orWhere('phone', 'like', $like);
                });
            })
            ->withSum('sales as balance', 'due_amount')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (PharmacyCustomerModel $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'balance' => round((float) $customer->balance, 2),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function accounts(?string $term, ?string $period = null, ?string $date = null): array
    {
        $term = trim((string) $term);
        $hospitalId = $this->sales->hospitalId();
        $like = '%'.addcslashes($term, '%_\\').'%';
        [$from, $to] = $this->range($period, $date);

        $sales = PharmacySaleModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('due_amount', '>', 0)
            ->when($from !== null && $to !== null, fn ($query) => $query->whereBetween('sold_at', [$from, $to]))
            ->whereHas('customer', function ($query) use ($term, $like): void {
                if ($term === '') {
                    return;
                }
                $query->where('name', 'like', $like)->orWhere('phone', 'like', $like);
            })
            ->with('customer')
            ->orderByDesc('sold_at')
            ->get();

        return [
            'outstanding' => round((float) $sales->sum('due_amount'), 2),
            'rows' => $sales->map(fn (PharmacySaleModel $sale) => [
                'id' => $sale->id,
                'customer_id' => $sale->pharmacy_customer_id,
                'customer_name' => $sale->customer?->name,
                'phone' => $sale->customer?->phone,
                'sale_number' => $sale->sale_number,
                'sold_at' => $sale->sold_at?->toIso8601String(),
                'total' => $sale->total,
                'due_amount' => $sale->due_amount,
            ])->all(),
        ];
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function range(?string $period, ?string $date): array
    {
        $day = null;
        if (is_string($date) && trim($date) !== '') {
            try {
                $day = Carbon::parse($date)->startOfDay();
            } catch (\Throwable) {
                $day = null;
            }
        }

        if ($day !== null) {
            return [$day, $day->copy()->endOfDay()];
        }

        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(Carbon::SATURDAY), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            default => [null, null],
        };
    }

    public function collect(int $customerId, float $amount, ?int $saleId = null, ?string $note = null): PharmacyCustomerModel
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw BusinessRuleException::make('مبلغ التحصيل يجب أن يكون أكبر من صفر');
        }

        $hospitalId = $this->sales->hospitalId();
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        return DB::transaction(function () use ($customerId, $amount, $saleId, $note, $hospitalId): PharmacyCustomerModel {
            $customer = PharmacyCustomerModel::query()
                ->whereKey($customerId)
                ->where('hospital_id', $hospitalId)
                ->lockForUpdate()
                ->first();

            if ($customer === null) {
                throw BusinessRuleException::make('الزبون غير موجود');
            }

            $open = PharmacySaleModel::query()
                ->where('pharmacy_customer_id', $customer->getKey())
                ->where('hospital_id', $hospitalId)
                ->where('due_amount', '>', 0)
                ->when($saleId !== null, fn ($query) => $query->whereKey($saleId))
                ->orderBy('sold_at')
                ->lockForUpdate()
                ->get();

            if ($open->isEmpty()) {
                throw BusinessRuleException::make('لا يوجد دين على هذا الزبون');
            }

            $balance = round((float) $open->sum('due_amount'), 2);
            if ($amount > $balance) {
                throw BusinessRuleException::make('المبلغ أكبر من الدين');
            }

            $left = $amount;
            foreach ($open as $sale) {
                if ($left <= 0) {
                    break;
                }
                $take = round(min($left, (float) $sale->due_amount), 2);
                $sale->due_amount = round((float) $sale->due_amount - $take, 2);
                $sale->save();

                PharmacyDebtPaymentModel::query()->create([
                    'hospital_id' => $hospitalId,
                    'pharmacy_customer_id' => $customer->getKey(),
                    'pharmacy_sale_id' => $sale->getKey(),
                    'user_id' => $this->currentUser->id(),
                    'amount' => $take,
                    'note' => $note,
                    'paid_at' => now(),
                ]);
                $left = round($left - $take, 2);
            }

            return $customer;
        });
    }
}
