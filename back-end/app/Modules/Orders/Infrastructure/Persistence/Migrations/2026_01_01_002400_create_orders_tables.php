<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The single request table for the whole hospital (db.md §17): rather
        // than lab_requests + radiology_requests + pharmacy_requests, every
        // department receives work through `orders` and enriches it in its own
        // tables.
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->foreignId('encounter_id')->constrained('encounters');
            $table->string('order_number', 50);

            $table->enum('type', [
                'laboratory', 'radiology', 'pharmacy',
                'procedure', 'consultation', 'supply', 'other',
            ]);
            $table->enum('status', ['pending', 'accepted', 'in_progress', 'completed', 'cancelled'])
                ->default('pending');
            $table->enum('priority', ['routine', 'urgent', 'stat'])->default('routine');

            $table->foreignId('ordered_by')->constrained('users');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

            $table->text('clinical_notes')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->dateTime('ordered_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'order_number']);
            $table->index(['type', 'status', 'priority']);
            $table->index(['encounter_id', 'type']);
            $table->index(['department_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // `service_type` + `service_id` point at whichever catalogue owns
            // the item (lab_tests, radiology_services, medications, ...).
            $table->string('service_type', 100);
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('name');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->text('instructions')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['service_type', 'service_id']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
