<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Laboratory\Application\Support\LabBarcode;
use App\Modules\Laboratory\Application\Support\LabOrderSource;
use App\Modules\Laboratory\Domain\Enums\BloodRequestStatus;
use App\Modules\Laboratory\Domain\Events\BloodRequestCreated;
use App\Modules\Laboratory\Domain\Exceptions\BloodRequestException;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodProductModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodRequestModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Support\Facades\DB;

final class CreateBloodRequestAction
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): BloodRequestModel
    {
        $patient = PatientModel::query()->forCurrentHospital()->findOrFail($data['patient_id']);
        $encounter = EncounterModel::query()->findOrFail($data['encounter_id']);

        if ((int) $encounter->patient_id !== (int) $patient->getKey()) {
            throw BloodRequestException::wrongPatient();
        }

        if (! $encounter->isOpen()) {
            throw BloodRequestException::encounterClosed();
        }

        $product = BloodProductModel::query()->findOrFail($data['blood_product_id']);

        if (! $product->is_active) {
            throw BloodRequestException::productUnavailable();
        }

        $encounter->loadMissing(['clinic', 'doctor.staff', 'department']);
        $source = LabOrderSource::fromVisit($encounter, $this->currentUser->model());

        return DB::transaction(function () use ($patient, $encounter, $product, $data, $source): BloodRequestModel {
            $request = BloodRequestModel::query()->create([
                'hospital_id' => $patient->hospital_id ?? $this->currentUser->hospitalId(),
                'patient_id' => $patient->getKey(),
                'encounter_id' => $encounter->getKey(),
                'barcode' => LabBarcode::nextBlood(),
                'clinic_name' => $source['clinic'],
                'blood_product_id' => $product->getKey(),
                'product_type' => $product->code,
                'product_name' => $product->name,
                'blood_group' => $data['blood_group'],
                'units' => $data['units'],
                'unit_price' => $product->price,
                'status' => BloodRequestStatus::Requested,
                'requested_by' => $this->currentUser->id(),
            ]);

            BloodRequestCreated::dispatch($request);

            return $request->load(['patient', 'requestedBy.staff']);
        });
    }
}
