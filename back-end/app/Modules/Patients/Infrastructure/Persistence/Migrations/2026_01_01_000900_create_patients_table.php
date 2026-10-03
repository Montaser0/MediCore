<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The permanent medical file. A patient is registered once and keeps the same
 * MRN for life; every subsequent visit becomes an encounter instead of a new
 * patient record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->string('mrn', 50);

            $table->string('first_name', 100);
            $table->string('father_name', 100)->nullable();
            $table->string('grandfather_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('mother_name', 200)->nullable();

            $table->enum('gender', ['male', 'female', 'other']);
            $table->date('date_of_birth')->nullable();

            $table->string('national_id', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('phone_secondary', 50)->nullable();
            $table->string('email')->nullable();

            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('nationality', 100)->nullable();

            $table->string('blood_type', 10)->nullable();
            $table->string('marital_status', 30)->nullable();
            $table->string('occupation', 150)->nullable();

            $table->enum('status', ['active', 'inactive', 'deceased'])->default('active');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['hospital_id', 'mrn'], 'uq_patient_mrn');
            $table->index(['last_name', 'first_name'], 'idx_patient_name');
            $table->index('phone', 'idx_patient_phone');
            $table->index('national_id', 'idx_patient_national_id');
            $table->index('date_of_birth', 'idx_patient_dob');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
