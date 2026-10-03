<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\EncounterContext;
use App\Modules\Billing\Domain\Enums\ChargeSource;
use App\Modules\Billing\Domain\Events\ChargeRaised;
use App\Modules\Billing\Infrastructure\Persistence\Models\ChargeModel;
use App\Modules\Billing\Infrastructure\Persistence\Models\ServiceModel;
use Illuminate\Support\Facades\DB;

/**
 * Raises one billable line. Automatic charges pass a source so the same
 * laboratory test or dispense can never be billed twice.
 */
final class CreateChargeAction
{
    public function __construct(
        private readonly EncounterContext $encounters,
        private readonly CurrentUser $currentUser,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): ChargeModel
    {
        // A charge belongs to a visit but may be raised after discharge, so
        // the encounter is read rather than required to be open.
        $encounter = $this->encounters->snapshot((int) $data['encounter_id']);

        $service = isset($data['service_id'])
            ? ServiceModel::query()->find($data['service_id'])
            : null;

        $description = $data['description'] ?? $service?->name;

        if ($description === null) {
            throw BusinessRuleException::make('البند يحتاج وصفاً أو خدمة.');
        }

        $quantity = (float) ($data['quantity'] ?? 1);
        $unitPrice = (float) ($data['unit_price'] ?? $service?->price ?? 0);
        $discount = (float) ($data['discount'] ?? 0);

        if ($quantity <= 0) {
            throw BusinessRuleException::make('كمية البند يجب أن تكون أكبر من صفر.');
        }

        $gross = round($quantity * $unitPrice, 2);

        if ($discount > $gross) {
            throw BusinessRuleException::make('الخصم لا يمكن أن يتجاوز قيمة البند.');
        }

        $sourceType = $data['source_type'] ?? ChargeSource::Manual->value;
        $sourceId = isset($data['source_id']) ? (int) $data['source_id'] : null;

        return DB::transaction(function () use (
            $encounter,
            $service,
            $description,
            $quantity,
            $unitPrice,
            $discount,
            $gross,
            $sourceType,
            $sourceId,
        ): ChargeModel {
            if ($sourceId !== null) {
                $existing = ChargeModel::query()
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $charge = ChargeModel::query()->create([
                'hospital_id' => $encounter->hospitalId,
                'patient_id' => $encounter->patientId,
                'encounter_id' => $encounter->id,
                'service_id' => $service?->getKey(),
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'total' => round($gross - $discount, 2),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'created_by' => $this->currentUser->id(),
            ]);

            ChargeRaised::dispatch($charge);

            return $charge;
        });
    }
}
