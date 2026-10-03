<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name', 150);
            $table->string('code', 50);
            $table->string('type', 50)->nullable();
            $table->boolean('is_pharmacy')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('sku', 100)->unique();
            $table->string('barcode', 100)->nullable();
            $table->string('category', 150)->nullable();
            $table->string('unit', 50);
            $table->enum('item_type', ['medicine', 'medical_supply', 'equipment', 'general']);
            $table->decimal('minimum_stock', 12, 2)->default(0);
            $table->decimal('reorder_quantity', 12, 2)->default(0);
            $table->boolean('is_batch_tracked')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['item_type', 'is_active']);
            $table->index('barcode');
        });

        Schema::create('inventory_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('batch_number', 100);
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->timestamps();

            $table->index('expiry_date');
            $table->index(['inventory_item_id', 'warehouse_id']);
            // MySQL caps identifiers at 64 characters, so the generated name
            // would be rejected.
            $table->unique(['inventory_item_id', 'warehouse_id', 'batch_number'], 'uq_batch_item_warehouse');
        });

        // Running total per item per warehouse. Derivable from the batches,
        // but kept as a row so the stock screen and availability checks do not
        // aggregate on every request.
        Schema::create('stock_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('reserved_quantity', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['inventory_item_id', 'warehouse_id']);
        });

        // Append-only ledger: every gram that moves is recorded (db.md §33).
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->enum('type', [
                'purchase', 'sale', 'dispense', 'transfer_in',
                'transfer_out', 'adjustment', 'return', 'expired',
            ]);
            $table->decimal('quantity', 12, 2);
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['inventory_item_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('warehouses');
    }
};
