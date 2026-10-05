<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyStocktakeLineModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PharmacyStocktakeModel;
use Illuminate\Support\Facades\DB;

final class PharmacyStocktakeAction
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly CreatePharmacySaleAction $sales,
    ) {}

    public function open(): PharmacyStocktakeModel
    {
        $hospitalId = $this->sales->hospitalId();
        $existing = PharmacyStocktakeModel::query()
            ->where('hospital_id', $hospitalId)
            ->where('status', 'draft')
            ->with('lines')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($hospitalId): PharmacyStocktakeModel {
            $stocktake = PharmacyStocktakeModel::query()->create([
                'hospital_id' => $hospitalId,
                'user_id' => $this->currentUser->id(),
                'stocktake_number' => 'PENDING',
                'status' => 'draft',
            ]);
            $stocktake->stocktake_number = 'STK-'.now()->format('Y').'-'.str_pad((string) $stocktake->getKey(), 5, '0', STR_PAD_LEFT);
            $stocktake->save();

            $medications = MedicationModel::query()
                ->where('hospital_id', $hospitalId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            foreach ($medications as $medication) {
                PharmacyStocktakeLineModel::query()->create([
                    'pharmacy_stocktake_id' => $stocktake->getKey(),
                    'medication_id' => $medication->getKey(),
                    'name' => $medication->name,
                    'barcode' => $medication->barcode,
                ]);
            }

            return $stocktake->load('lines');
        });
    }

    public function current(): ?PharmacyStocktakeModel
    {
        return PharmacyStocktakeModel::query()
            ->where('hospital_id', $this->sales->hospitalId())
            ->where('status', 'draft')
            ->with('lines')
            ->first();
    }

    public function count(int $stocktakeId, int $lineId, float $counted): PharmacyStocktakeLineModel
    {
        $counted = round($counted, 2);
        if ($counted < 0) {
            throw BusinessRuleException::make('الكمية المعدودة لا تكون سالبة');
        }

        $hospitalId = $this->sales->hospitalId();

        return DB::transaction(function () use ($stocktakeId, $lineId, $counted, $hospitalId): PharmacyStocktakeLineModel {
            $stocktake = $this->draft($stocktakeId, $hospitalId);
            $line = PharmacyStocktakeLineModel::query()
                ->whereKey($lineId)
                ->where('pharmacy_stocktake_id', $stocktake->getKey())
                ->lockForUpdate()
                ->first();

            if ($line === null) {
                throw BusinessRuleException::make('سطر الجرد غير موجود');
            }

            $medication = MedicationModel::query()
                ->whereKey($line->medication_id)
                ->where('hospital_id', $hospitalId)
                ->lockForUpdate()
                ->first();

            if ($medication === null) {
                throw BusinessRuleException::make('الدواء لم يعد على الرف');
            }

            $line->system_quantity = round((float) $medication->quantity, 2);
            $line->counted_quantity = $counted;
            $line->save();

            return $line;
        });
    }

    public function post(int $stocktakeId): PharmacyStocktakeModel
    {
        $hospitalId = $this->sales->hospitalId();

        return DB::transaction(function () use ($stocktakeId, $hospitalId): PharmacyStocktakeModel {
            $stocktake = $this->draft($stocktakeId, $hospitalId);
            $lines = PharmacyStocktakeLineModel::query()
                ->where('pharmacy_stocktake_id', $stocktake->getKey())
                ->lockForUpdate()
                ->get();

            $counted = $lines->filter(fn (PharmacyStocktakeLineModel $line) => $line->counted_quantity !== null);
            if ($counted->isEmpty()) {
                throw BusinessRuleException::make('لم يُعد أي صنف بعد');
            }

            foreach ($counted as $line) {
                $medication = MedicationModel::query()
                    ->whereKey($line->medication_id)
                    ->where('hospital_id', $hospitalId)
                    ->lockForUpdate()
                    ->first();

                if ($medication === null || $line->system_quantity === null) {
                    throw BusinessRuleException::make('تعذر ترحيل الجرد: '.$line->name);
                }

                $variance = round((float) $line->counted_quantity - (float) $line->system_quantity, 2);
                $next = round((float) $medication->quantity + $variance, 2);
                if ($next < 0) {
                    throw BusinessRuleException::make('الجرد يجعل الرصيد سالباً: '.$line->name);
                }

                $medication->quantity = $next;
                $medication->save();
            }

            $stocktake->status = 'posted';
            $stocktake->posted_at = now();
            $stocktake->save();

            return $stocktake->load('lines');
        });
    }

    private function draft(int $stocktakeId, int $hospitalId): PharmacyStocktakeModel
    {
        $stocktake = PharmacyStocktakeModel::query()
            ->whereKey($stocktakeId)
            ->where('hospital_id', $hospitalId)
            ->lockForUpdate()
            ->first();

        if ($stocktake === null || $stocktake->status !== 'draft') {
            throw BusinessRuleException::make('لا يوجد جرد مفتوح');
        }

        return $stocktake;
    }
}
