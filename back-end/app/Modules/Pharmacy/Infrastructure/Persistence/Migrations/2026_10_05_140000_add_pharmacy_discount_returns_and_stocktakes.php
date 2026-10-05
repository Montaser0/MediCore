<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_sales', function (Blueprint $table): void {
            $table->decimal('subtotal', 12, 2)->default(0)->after('sale_number');
            $table->decimal('discount', 12, 2)->default(0)->after('subtotal');
        });

        Schema::create('pharmacy_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('pharmacy_sale_id')->constrained('pharmacy_sales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_number');
            $table->decimal('total', 12, 2)->default(0);
            $table->string('reason')->nullable();
            $table->timestamp('returned_at');
            $table->timestamps();

            $table->unique(['hospital_id', 'return_number']);
            $table->index(['hospital_id', 'returned_at']);
        });

        Schema::create('pharmacy_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pharmacy_return_id')->constrained('pharmacy_returns')->cascadeOnDelete();
            $table->foreignId('pharmacy_sale_item_id')->constrained('pharmacy_sale_items')->cascadeOnDelete();
            $table->foreignId('medication_id')->nullable()->constrained('medications')->nullOnDelete();
            $table->string('name');
            $table->string('barcode')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pharmacy_stocktakes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stocktake_number');
            $table->string('status', 16)->default('draft');
            $table->string('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'stocktake_number']);
            $table->index(['hospital_id', 'status']);
        });

        Schema::create('pharmacy_stocktake_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pharmacy_stocktake_id')->constrained('pharmacy_stocktakes')->cascadeOnDelete();
            $table->foreignId('medication_id')->constrained('medications')->cascadeOnDelete();
            $table->string('name');
            $table->string('barcode')->nullable();
            $table->decimal('system_quantity', 12, 2)->nullable();
            $table->decimal('counted_quantity', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['pharmacy_stocktake_id', 'medication_id'], 'stocktake_line_medication_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_stocktake_lines');
        Schema::dropIfExists('pharmacy_stocktakes');
        Schema::dropIfExists('pharmacy_return_items');
        Schema::dropIfExists('pharmacy_returns');
        Schema::table('pharmacy_sales', function (Blueprint $table): void {
            $table->dropColumn(['subtotal', 'discount']);
        });
    }
};
