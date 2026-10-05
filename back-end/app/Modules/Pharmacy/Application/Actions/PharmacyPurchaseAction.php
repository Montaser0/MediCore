<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyPurchaseModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySupplierModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySupplierPaymentModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class PharmacyPurchaseAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly CreatePharmacySaleAction $sales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function list(?string $term, ?string $period = null, ?string $date = null): array
    {
        $term = trim((string) $term);
        $hospitalId = $this->sales->hospitalId();
        $like = '%'.addcslashes($term, '%_\\').'%';
        [$from, $to] = $this->range($period, $date);

        $rows = PharmacyPurchaseModel::query()
            ->where('hospital_id', $hospitalId)
            ->when($from !== null && $to !== null, fn ($query) => $query->whereBetween('received_at', [$from, $to]))
            ->when($term !== '', function ($query) use ($like): void {
                $query->where(function ($inner) use ($like): void {
                    $inner->where('invoice_number', 'like', $like)
                        ->orWhereHas('supplier', function ($supplier) use ($like): void {
                            $supplier->where('name', 'like', $like)->orWhere('phone', 'like', $like);
                        });
                });
            })
            ->with('supplier')
            ->latest('received_at')
            ->limit(200)
            ->get();

        return [
            'outstanding' => round((float) PharmacyPurchaseModel::query()
                ->where('hospital_id', $hospitalId)
                ->when($from !== null && $to !== null, fn ($query) => $query->whereBetween('received_at', [$from, $to]))
                ->sum('due_amount'), 2),
            'rows' => $rows->map(fn (PharmacyPurchaseModel $purchase) => [
                'id' => $purchase->id,
                'supplier_id' => $purchase->pharmacy_supplier_id,
                'supplier_name' => $purchase->supplier?->name,
                'phone' => $purchase->supplier?->phone,
                'invoice_number' => $purchase->invoice_number,
                'total' => $purchase->total,
                'cash_amount' => $purchase->cash_amount,
                'due_amount' => $purchase->due_amount,
                'received_at' => $purchase->received_at?->toIso8601String(),
            ])->all(),
        ];
    }

    public function store(
        string $supplierName,
        ?string $phone,
        ?int $supplierId,
        string $invoiceNumber,
        float $total,
        float $cashAmount,
        ?string $receivedAt,
    ): PharmacyPurchaseModel {
        $total = round($total, 2);
        $cashAmount = round(max($cashAmount, 0), 2);
        if ($total <= 0) {
            throw BusinessRuleException::make('مبلغ المصروف يجب أن يكون أكبر من صفر');
        }
        if ($cashAmount > $total) {
            throw BusinessRuleException::make('المدفوع أكبر من قيمة المصروف');
        }

        $invoiceNumber = trim($invoiceNumber);
        if ($invoiceNumber === '') {
            throw BusinessRuleException::make('اكتب رقم الفاتورة');
        }

        $hospitalId = $this->sales->hospitalId();

        return DB::transaction(function () use ($supplierName, $phone, $supplierId, $invoiceNumber, $total, $cashAmount, $receivedAt, $hospitalId): PharmacyPurchaseModel {
            $duplicate = PharmacyPurchaseModel::query()
                ->where('hospital_id', $hospitalId)
                ->where('invoice_number', $invoiceNumber)
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw BusinessRuleException::make('رقم الفاتورة مسجّل من قبل');
            }

            $supplier = $this->supplier($hospitalId, $supplierId, $supplierName, $phone);
            $received = $receivedAt !== null && trim($receivedAt) !== ''
                ? Carbon::parse($receivedAt)->startOfDay()
                : now();

            return PharmacyPurchaseModel::query()->create([
                'hospital_id' => $hospitalId,
                'pharmacy_supplier_id' => $supplier->getKey(),
                'user_id' => $this->currentUser->id(),
                'invoice_number' => $invoiceNumber,
                'total' => $total,
                'cash_amount' => $cashAmount,
                'due_amount' => round($total - $cashAmount, 2),
                'received_at' => $received,
            ]);
        });
    }

    public function pay(int $purchaseId, float $amount): void
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw BusinessRuleException::make('مبلغ الدفعة يجب أن يكون أكبر من صفر');
        }

        $hospitalId = $this->sales->hospitalId();

        DB::transaction(function () use ($purchaseId, $amount, $hospitalId): void {
            $purchase = PharmacyPurchaseModel::query()
                ->whereKey($purchaseId)
                ->where('hospital_id', $hospitalId)
                ->lockForUpdate()
                ->first();

            if ($purchase === null) {
                throw BusinessRuleException::make('المصروف غير موجود');
            }
            if ($amount > (float) $purchase->due_amount) {
                throw BusinessRuleException::make('المبلغ أكبر من الدين');
            }

            $purchase->due_amount = round((float) $purchase->due_amount - $amount, 2);
            $purchase->save();

            PharmacySupplierPaymentModel::query()->create([
                'hospital_id' => $hospitalId,
                'pharmacy_supplier_id' => $purchase->pharmacy_supplier_id,
                'pharmacy_purchase_id' => $purchase->getKey(),
                'user_id' => $this->currentUser->id(),
                'amount' => $amount,
                'paid_at' => now(),
            ]);
        });
    }

    private function supplier(int $hospitalId, ?int $supplierId, string $name, ?string $phone): PharmacySupplierModel
    {
        if ($supplierId !== null) {
            $supplier = PharmacySupplierModel::query()
                ->whereKey($supplierId)
                ->where('hospital_id', $hospitalId)
                ->lockForUpdate()
                ->first();
            if ($supplier === null) {
                throw BusinessRuleException::make('المصروف غير موجود');
            }

            return $supplier;
        }

        $name = trim($name);
        if ($name === '') {
            throw BusinessRuleException::make('اكتب البيان');
        }
        $phone = trim((string) $phone);

        $existing = PharmacySupplierModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('name', $name)
            ->where(function ($query) use ($phone): void {
                if ($phone === '') {
                    $query->whereNull('phone');
                } else {
                    $query->where('phone', $phone);
                }
            })
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return PharmacySupplierModel::query()->create([
            'hospital_id' => $hospitalId,
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
        ]);
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function range(?string $period, ?string $date): array
    {
        if (is_string($date) && trim($date) !== '') {
            try {
                $day = Carbon::parse($date)->startOfDay();

                return [$day, $day->copy()->endOfDay()];
            } catch (\Throwable) {
                return [null, null];
            }
        }

        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(Carbon::SATURDAY), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            default => [null, null],
        };
    }
}
