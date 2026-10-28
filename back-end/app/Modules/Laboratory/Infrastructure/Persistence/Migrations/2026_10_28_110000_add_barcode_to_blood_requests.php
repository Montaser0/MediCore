<?php

declare(strict_types=1);

use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Laboratory\Application\Support\LabBarcode;
use App\Modules\Laboratory\Application\Support\LabOrderSource;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodRequestModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blood_requests') || Schema::hasColumn('blood_requests', 'barcode')) {
            return;
        }

        Schema::table('blood_requests', function (Blueprint $table): void {
            $table->string('barcode', 13)->nullable()->unique()->after('encounter_id');
            $table->string('clinic_name', 150)->nullable()->after('barcode');
        });

        BloodRequestModel::query()->orderBy('id')->each(function (BloodRequestModel $request): void {
            $encounter = EncounterModel::query()->find($request->encounter_id);
            $encounter?->loadMissing(['clinic', 'doctor.staff', 'department']);
            $names = LabOrderSource::fromVisit($encounter, null);

            $request->forceFill([
                'barcode' => $request->barcode ?: LabBarcode::nextBlood(),
                'clinic_name' => $names['clinic'],
            ])->save();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('blood_requests') || ! Schema::hasColumn('blood_requests', 'barcode')) {
            return;
        }

        Schema::table('blood_requests', function (Blueprint $table): void {
            $table->dropUnique(['barcode']);
            $table->dropColumn(['barcode', 'clinic_name']);
        });
    }
};
