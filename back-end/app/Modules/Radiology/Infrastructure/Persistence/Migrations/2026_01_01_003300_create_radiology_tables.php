<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiology_services', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('modality', 100)->nullable();
            $table->text('description')->nullable();
            $table->text('preparation_instructions')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('requires_contrast')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['modality', 'is_active']);
        });

        Schema::create('radiology_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->text('clinical_indication')->nullable();
            $table->timestamps();
        });

        Schema::create('radiology_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('radiology_order_id')->constrained('radiology_orders')->cascadeOnDelete();
            $table->foreignId('radiology_service_id')->constrained('radiology_services');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->enum('status', ['pending', 'scheduled', 'in_progress', 'completed', 'reported', 'cancelled'])
                ->default('pending');
            $table->timestamps();

            $table->index(['radiology_order_id', 'status']);
            $table->index('status');
        });

        // The imaging session itself. Images are attached through the shared
        // Documents module rather than a radiology-specific file table.
        Schema::create('radiology_studies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('radiology_order_id')->constrained('radiology_orders')->cascadeOnDelete();
            $table->foreignId('radiology_order_item_id')->nullable()
                ->constrained('radiology_order_items')->nullOnDelete();
            $table->string('study_number', 100)->unique();
            $table->string('accession_number', 100)->nullable();
            $table->string('study_instance_uid')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('performed_at')->nullable();
            $table->unsignedSmallInteger('image_count')->default(0);
            $table->text('technician_notes')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('radiology_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('study_id')->unique()->constrained('radiology_studies')->cascadeOnDelete();
            $table->longText('findings')->nullable();
            $table->longText('impression')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reported_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_reports');
        Schema::dropIfExists('radiology_studies');
        Schema::dropIfExists('radiology_order_items');
        Schema::dropIfExists('radiology_orders');
        Schema::dropIfExists('radiology_services');
    }
};
