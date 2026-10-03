<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 50)->unique();
            $table->string('specialty', 100)->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->default(60);
            $table->string('risk_level', 30)->default('moderate');
            $table->decimal('price', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('operation_theatres', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('encounter_id')->constrained('encounters');
            $table->foreignId('operation_type_id')->constrained('operation_types');
            $table->foreignId('theatre_id')->nullable()->constrained('operation_theatres');
            $table->string('operation_number', 50)->unique();
            $table->string('status', 30)->default('scheduled');
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('estimated_duration_minutes')->default(60);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();

            // Pre-operative assessment (PRD §27).
            $table->text('pre_op_diagnosis')->nullable();
            $table->string('anesthesia_type', 50)->nullable();
            $table->boolean('consent_obtained')->default(false);
            $table->dateTime('consent_obtained_at')->nullable();
            $table->text('pre_op_notes')->nullable();

            // Post-operative record.
            $table->text('post_op_diagnosis')->nullable();
            $table->text('post_op_instructions')->nullable();
            $table->text('complications')->nullable();
            $table->string('outcome', 50)->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['hospital_id', 'status']);
            $table->index('scheduled_at');
        });

        Schema::create('operation_team', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operation_id')->constrained('operations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('role', 50);
            $table->text('notes')->nullable();
            $table->timestamps();

            // One person holds one role on one operation.
            $table->unique(['operation_id', 'user_id', 'role']);
        });

        Schema::create('operation_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operation_id')->constrained('operations')->cascadeOnDelete();
            $table->string('note_type', 50)->default('operative');
            $table->longText('content');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['operation_id', 'note_type']);
        });

        // Consumables and implants used in theatre, issued from inventory.
        Schema::create('operation_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operation_id')->constrained('operations')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_materials');
        Schema::dropIfExists('operation_notes');
        Schema::dropIfExists('operation_team');
        Schema::dropIfExists('operations');
        Schema::dropIfExists('operation_theatres');
        Schema::dropIfExists('operation_types');
    }
};
