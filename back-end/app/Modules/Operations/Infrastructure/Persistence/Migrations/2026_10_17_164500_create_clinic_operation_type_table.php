<?php

declare(strict_types=1);

use App\Modules\Operations\Infrastructure\Persistence\OperationClinicLinks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each clinic keeps the operations its doctors and clerks are allowed to add,
 * together with the price that lands on the patient account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_operation_type', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('operation_type_id')->constrained('operation_types')->cascadeOnDelete();
            $table->unique(['clinic_id', 'operation_type_id'], 'uq_clinic_operation_type');
        });

        OperationClinicLinks::ensureDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_operation_type');
    }
};
