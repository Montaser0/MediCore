<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_counter_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->string('status', 20)->default('waiting');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('sent_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'status']);
            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_counter_tickets');
    }
};
