<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A visit. Everything clinical or financial that happens in the hospital hangs
 * off an encounter_id, which is what makes the same test repeatable across
 * years without ambiguity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->foreignId('patient_id')->constrained('patients');
            $table->string('encounter_number', 50);

            $table->enum('type', ['outpatient', 'emergency', 'inpatient', 'operation', 'follow_up']);
            $table->enum('status', ['registered', 'waiting', 'in_progress', 'completed', 'cancelled'])
                ->default('registered');

            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('clinic_id')->nullable()->constrained('clinics');
            $table->foreignId('doctor_id')->nullable()->constrained('doctors');

            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();

            $table->text('reason')->nullable();
            $table->text('chief_complaint')->nullable();

            // Discharge details (PRD §40): recorded when the visit is closed.
            $table->text('discharge_instructions')->nullable();
            $table->string('discharge_disposition', 100)->nullable();
            $table->date('follow_up_date')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['hospital_id', 'encounter_number'], 'uq_encounter_number');
            $table->index('patient_id', 'idx_encounter_patient');
            $table->index('status', 'idx_encounter_status');
            $table->index('created_at', 'idx_encounter_date');
            $table->index(['department_id', 'status'], 'idx_encounter_department_queue');
            $table->index(['doctor_id', 'status'], 'idx_encounter_doctor_queue');
        });

        Schema::create('encounter_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments');
            $table->foreignId('to_department_id')->constrained('departments');
            $table->foreignId('from_user_id')->nullable()->constrained('users');
            $table->foreignId('to_user_id')->nullable()->constrained('users');
            $table->text('reason')->nullable();
            $table->dateTime('transferred_at');
            $table->enum('status', ['pending', 'accepted', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['to_department_id', 'status'], 'idx_transfer_inbox');
        });

        Schema::create('encounter_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('encounter_id', 'idx_status_history_encounter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_status_history');
        Schema::dropIfExists('encounter_transfers');
        Schema::dropIfExists('encounters');
    }
};
