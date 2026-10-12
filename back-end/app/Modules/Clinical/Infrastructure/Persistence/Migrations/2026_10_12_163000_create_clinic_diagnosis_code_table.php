<?php

declare(strict_types=1);

use App\Modules\Clinical\Infrastructure\Persistence\DiagnosisClinicLinks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each clinic keeps the diagnoses its doctors are allowed to pick.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_diagnosis_code', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('diagnosis_code_id')->constrained('diagnosis_codes')->cascadeOnDelete();
            $table->unique(['clinic_id', 'diagnosis_code_id'], 'uq_clinic_diagnosis_code');
        });

        DiagnosisClinicLinks::ensureDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_diagnosis_code');
    }
};
