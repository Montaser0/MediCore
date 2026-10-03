<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->foreignId('patient_id')->constrained('patients');
            $table->string('appointment_number', 50);

            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('clinic_id')->nullable()->constrained('clinics');
            $table->foreignId('doctor_id')->nullable()->constrained('doctors');

            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(15);

            $table->enum('status', [
                'scheduled', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show',
            ])->default('scheduled');

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            // Set when the appointment is checked in and becomes a live visit.
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['hospital_id', 'appointment_number'], 'uq_appointment_number');
            $table->index(['scheduled_at', 'status'], 'idx_appointment_schedule');
            $table->index(['doctor_id', 'scheduled_at'], 'idx_appointment_doctor_day');
            $table->index('patient_id', 'idx_appointment_patient');
        });

        Schema::create('appointment_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('appointment_id', 'idx_appointment_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_history');
        Schema::dropIfExists('appointments');
    }
};
