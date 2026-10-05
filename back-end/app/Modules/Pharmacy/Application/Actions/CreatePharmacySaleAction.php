<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyCustomerModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacySaleModel;
use Illuminate\Support\Facades\DB;

final class CreatePharmacySaleAction
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    /**
     * @param  array<int, array{medication_id: int, quantity: float|int|string}>  $lines
     */
    public function execute(
        array $lines,
        float $discount = 0,
        ?float $cashAmount = null,
        ?int $customerId = null,
        ?string $customerName = null,
        ?string $customerPhone = null,
    ): PharmacySaleModel {
        if ($lines === []) {
            throw BusinessRuleException::make('أضف دواءً قبل إتمام البيع');
        }

        $discount = round(max($discount, 0), 2);
        $hospitalId = $this->hospitalId();
        $userId = $this->currentUser->id();

        return DB::transaction(function () use ($lines, $hospitalId, $userId, $discount, $cashAmount, $customerId, $customerName, $customerPhone): PharmacySaleModel {
            $sale = PharmacySaleModel::query()->create([
                'hospital_id' => $hospitalId,
                'user_id' => $userId,
                'sale_number' => 'PENDING',
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
                'sold_at' => now(),
            ]);
            $sale->sale_number = 'POS-'.now()->format('Y').'-'.str_pad((string) $sale->getKey(), 5, '0', STR_PAD_LEFT);

            $total = 0.0;
            foreach ($lines as $line) {
                $quantity = round((float) $line['quantity'], 2);
                if ($quantity <= 0) {
                    throw BusinessRuleException::make('الكمية يجب أن تكون أكبر من صفر');
                }

                $medication = MedicationModel::query()
                    ->whereKey((int) $line['medication_id'])
                    ->where('hospital_id', $hospitalId)
                    ->lockForUpdate()
                    ->first();

                if ($medication === null || ! $medication->is_active) {
                    throw BusinessRuleException::make('الدواء غير متاح في هذه الصيدلية');
                }

                $onHand = round((float) $medication->quantity, 2);
                if ($onHand < $quantity) {
                    throw BusinessRuleException::make('الكمية غير كافية: '.$medication->name);
                }

                $medication->quantity = round($onHand - $quantity, 2);
                $medication->save();

                $lineTotal = round($quantity * (float) $medication->price, 2);
                $total += $lineTotal;

                PharmacySaleItemModel::query()->create([
                    'pharmacy_sale_id' => $sale->getKey(),
                    'medication_id' => $medication->getKey(),
                    'name' => $medication->name,
                    'barcode' => $medication->barcode,
                    'quantity' => $quantity,
                    'unit_price' => $medication->price,
                    'line_total' => $lineTotal,
                ]);
            }

            $subtotal = round($total, 2);
            if ($discount > $subtotal) {
                throw BusinessRuleException::make('الخصم أكبر من قيمة الفاتورة');
            }

            $net = round($subtotal - $discount, 2);
            $cash = $cashAmount === null ? $net : round(max($cashAmount, 0), 2);
            if ($cash > $net) {
                throw BusinessRuleException::make('المدفوع أكبر من قيمة الفاتورة');
            }

            $due = round($net - $cash, 2);
            $customer = null;
            if ($due > 0) {
                $customer = $this->customer($hospitalId, $customerId, $customerName, $customerPhone);
            }

            $sale->subtotal = $subtotal;
            $sale->discount = $discount;
            $sale->total = $net;
            $sale->cash_amount = $cash;
            $sale->due_amount = $due;
            $sale->pharmacy_customer_id = $customer?->getKey();
            $sale->save();

            return $sale->load(['items', 'customer']);
        });
    }

    public function hospitalId(): int
    {
        $hospitalId = $this->currentUser->hospitalId();
        if ($hospitalId !== null) {
            return $hospitalId;
        }

        $fallback = HospitalModel::query()
            ->where('code', (string) config('hms.default_hospital_code'))
            ->value('id');

        if ($fallback === null) {
            throw BusinessRuleException::make('الحساب غير مرتبط بمنشأة');
        }

        return (int) $fallback;
    }

    private function customer(int $hospitalId, ?int $customerId, ?string $name, ?string $phone): PharmacyCustomerModel
    {
        if ($customerId !== null) {
            $customer = PharmacyCustomerModel::query()
                ->whereKey($customerId)
                ->where('hospital_id', $hospitalId)
                ->lockForUpdate()
                ->first();

            if ($customer === null) {
                throw BusinessRuleException::make('الزبون غير موجود');
            }

            return $customer;
        }

        $name = trim((string) $name);
        if ($name === '') {
            throw BusinessRuleException::make('اكتب اسم صاحب الدين');
        }

        $phone = trim((string) $phone);
        $existing = PharmacyCustomerModel::query()
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

        return PharmacyCustomerModel::query()->create([
            'hospital_id' => $hospitalId,
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
        ]);
    }
}
