<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the patient timeline the PRD calls one of the most important screens
 * in the system. Every module appends to it through the TimelineRecorder
 * contract, so the timeline is a single ordered read instead of a union of a
 * dozen queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeline_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('event_type', 100);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('payload')->nullable();

            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['patient_id', 'occurred_at'], 'idx_timeline_patient');
            $table->index(['encounter_id', 'occurred_at'], 'idx_timeline_encounter');
            $table->index('event_type', 'idx_timeline_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_events');
    }
};
