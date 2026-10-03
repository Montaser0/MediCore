<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A login account is not a clinician. `users` holds credentials, `staff` holds
 * the employee, and `doctors` extends a staff row with licensing details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->string('employee_number', 50);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 50)->nullable();
            $table->string('job_title', 150)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'employee_number'], 'uq_employee_number');
            $table->index(['last_name', 'first_name'], 'idx_staff_name');
        });

        Schema::create('doctors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->unique('uq_doctor_staff')->constrained('staff');
            $table->string('license_number', 100)->nullable();
            $table->string('specialty', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('staff');
    }
};
