<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nursing_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('encounter_id')->constrained('encounters');
            $table->foreignId('admission_id')->nullable()->constrained('admissions');
            $table->string('note_type', 50)->default('progress');
            $table->string('shift', 20)->nullable();
            $table->longText('content');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['encounter_id', 'created_at']);
            $table->index(['admission_id', 'shift']);
        });

        Schema::create('care_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('encounter_id')->constrained('encounters');
            $table->foreignId('admission_id')->nullable()->constrained('admissions');
            $table->string('title', 255);
            $table->text('problem');
            $table->text('goal')->nullable();
            $table->text('interventions')->nullable();
            $table->text('evaluation')->nullable();
            $table->string('status', 30)->default('active');
            $table->date('start_date');
            $table->date('target_date')->nullable();
            $table->date('resolved_date')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['encounter_id', 'status']);
        });

        Schema::create('medication_administrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('encounter_id')->constrained('encounters');
            $table->foreignId('prescription_item_id')->nullable()->constrained('prescription_items');
            $table->foreignId('medication_id')->constrained('medications');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('administered_at')->nullable();
            // Mirrors the prescription's own free-text dose ("500 mg").
            $table->string('dose', 100)->nullable();
            $table->string('route', 100)->nullable();
            $table->string('status', 30)->default('due');
            // A refusal or an omission is as much a clinical fact as a dose
            // given, so it is recorded on the same row.
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('administered_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['encounter_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
        Schema::dropIfExists('care_plans');
        Schema::dropIfExists('nursing_notes');
    }
};
