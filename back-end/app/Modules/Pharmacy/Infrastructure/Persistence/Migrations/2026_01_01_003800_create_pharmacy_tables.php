<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_controlled')->default(false);
            $table->timestamps();
        });

        Schema::create('medications', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('strength', 100)->nullable();
            $table->string('dosage_form', 100)->nullable();
            $table->string('route', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('category', 150)->nullable();
            $table->foreignId('medication_category_id')->nullable()
                ->constrained('medication_categories')->nullOnDelete();

            // Dispensing draws real stock, so a medication points at the
            // inventory item that holds it. The foreign key is added in the
            // later migration that runs after `inventory_items` exists.
            $table->unsignedBigInteger('inventory_item_id')->nullable();

            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('requires_prescription')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['name', 'is_active']);
            $table->index('generic_name');
            $table->index('barcode');
        });

        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->foreignId('encounter_id')->constrained('encounters');
            $table->string('prescription_number', 50);
            $table->foreignId('doctor_id')->constrained('doctors');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->enum('status', ['draft', 'active', 'partially_dispensed', 'dispensed', 'cancelled'])
                ->default('draft');
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('prescribed_at');
            $table->timestamps();

            $table->unique(['hospital_id', 'prescription_number']);
            $table->index(['encounter_id', 'status']);
            $table->index('status');
        });

        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('medication_id')->constrained('medications');
            $table->string('dose', 100)->nullable();
            $table->string('frequency', 100)->nullable();
            $table->string('duration', 100)->nullable();
            $table->string('route', 100)->nullable();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->decimal('dispensed_quantity', 12, 2)->default(0);
            $table->text('instructions')->nullable();
            $table->timestamps();

            $table->index('prescription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('medications');
        Schema::dropIfExists('medication_categories');
    }
};
