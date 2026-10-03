<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments');
            $table->string('name', 150);
            $table->string('code', 50);
            $table->string('type', 50)->nullable();
            $table->unsignedInteger('floor')->nullable();
            $table->decimal('daily_rate', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->string('room_number', 50);
            $table->string('room_type', 50)->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->decimal('daily_rate', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['ward_id', 'room_number']);
        });

        Schema::create('beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('bed_number', 50);
            $table->string('status', 30)->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'bed_number']);
            $table->index('status');
        });

        Schema::create('admissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            // One admission per encounter: an inpatient stay is the visit.
            $table->foreignId('encounter_id')->unique()->constrained('encounters');
            $table->string('admission_number', 50)->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('attending_doctor_id')->nullable()->constrained('users');
            $table->foreignId('admitted_by')->constrained('users');
            $table->dateTime('admitted_at');
            $table->dateTime('expected_discharge_at')->nullable();
            $table->dateTime('discharged_at')->nullable();
            $table->string('status', 30)->default('admitted');
            $table->string('discharge_type', 30)->nullable();
            $table->text('admission_reason')->nullable();
            $table->longText('discharge_summary')->nullable();
            $table->foreignId('discharged_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['hospital_id', 'status']);
            $table->index('admitted_at');
        });

        Schema::create('bed_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('bed_id')->constrained('beds');
            $table->dateTime('assigned_at');
            $table->dateTime('released_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamps();

            // The open assignment is the one that matters, so it is indexed.
            $table->index(['bed_id', 'released_at']);
            $table->index(['admission_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bed_assignments');
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('wards');
    }
};
