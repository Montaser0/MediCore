<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the human readable identifiers (MRN, encounter number, invoice
 * number...). One row per hospital, sequence and year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->string('sequence_key', 50);
            $table->string('period', 10);
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();

            $table->unique(['hospital_id', 'sequence_key', 'period'], 'uq_number_sequence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
