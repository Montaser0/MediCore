<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runs after the inventory tables so the pharmacy can reference warehouses
 * and batches directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table): void {
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->nullOnDelete();
        });

        Schema::create('medication_dispenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->string('dispense_number', 50);
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $table->foreignId('prescription_item_id')->nullable()
                ->constrained('prescription_items')->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignId('medication_id')->constrained('medications');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('inventory_batch_id')->nullable()
                ->constrained('inventory_batches')->nullOnDelete();

            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->string('batch_number', 100)->nullable();
            $table->date('batch_expiry_date')->nullable();

            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dispensed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'dispense_number']);
            $table->index(['prescription_id', 'medication_id']);
            $table->index('dispensed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_dispenses');

        Schema::table('medications', function (Blueprint $table): void {
            $table->dropForeign(['inventory_item_id']);
        });
    }
};
