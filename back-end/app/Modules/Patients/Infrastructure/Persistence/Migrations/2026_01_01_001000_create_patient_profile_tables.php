<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Longitudinal facts that belong to the patient rather than to a single visit:
 * who to call, what they react to, and what they live with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('relationship', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('alternate_phone', 50)->nullable();
            $table->boolean('is_emergency_contact')->default(false);
            $table->timestamps();

            $table->index(['patient_id', 'is_emergency_contact'], 'idx_contact_emergency');
        });

        Schema::create('patient_allergies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('allergen', 200);
            $table->text('reaction')->nullable();
            $table->enum('severity', ['mild', 'moderate', 'severe', 'unknown'])->default('unknown');
            $table->enum('status', ['active', 'inactive', 'unknown'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status'], 'idx_allergy_status');
        });

        Schema::create('patient_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('condition_name');
            $table->date('diagnosed_at')->nullable();
            $table->enum('status', ['active', 'resolved', 'unknown'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status'], 'idx_condition_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_conditions');
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('patient_contacts');
    }
};
