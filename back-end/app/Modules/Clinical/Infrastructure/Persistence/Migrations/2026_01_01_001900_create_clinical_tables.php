<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reference list of coded diagnoses (ICD-10 by default). Doctors may
        // still free-type a diagnosis name, so the code link stays optional.
        Schema::create('diagnosis_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('system', 30)->default('icd10');
            $table->string('code', 30);
            $table->string('title');
            $table->string('category', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['system', 'code']);
            $table->index('title');
        });

        Schema::create('vital_signs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();

            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->decimal('height', 6, 2)->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['encounter_id', 'recorded_at']);
        });

        Schema::create('clinical_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->enum('note_type', ['general', 'history', 'examination', 'progress', 'discharge'])
                ->default('general');
            $table->longText('content');
            $table->timestamps();

            $table->index(['encounter_id', 'note_type']);
        });

        Schema::create('diagnoses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors');
            $table->foreignId('diagnosis_code_id')->nullable()->constrained('diagnosis_codes')->nullOnDelete();
            $table->string('diagnosis_name');
            $table->enum('type', ['provisional', 'primary', 'secondary', 'final'])->default('primary');
            $table->text('notes')->nullable();
            $table->dateTime('diagnosed_at');
            $table->timestamps();

            $table->index(['encounter_id', 'type']);
        });

        // Bedside / clinic procedures performed during a visit. Theatre cases
        // live in the Operations module instead.
        Schema::create('procedures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->text('outcome')->nullable();
            $table->dateTime('performed_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['encounter_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedures');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('vital_signs');
        Schema::dropIfExists('diagnosis_codes');
    }
};
