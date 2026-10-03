<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departments are data, not code: adding a dialysis unit or a blood bank is a
 * row here plus the matching order types, never a schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->string('name', 150);
            $table->string('code', 50);
            $table->string('type', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'code'], 'uq_department_code');
            $table->index('type', 'idx_department_type');
        });

        Schema::create('clinics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained('departments');
            $table->string('name', 150);
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'code'], 'uq_clinic_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
        Schema::dropIfExists('departments');
    }
};
