<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table): void {
            $table->foreignId('hospital_id')->nullable()->after('id')->constrained('hospitals');
            $table->decimal('quantity', 12, 2)->default(0)->after('price');
            $table->index(['hospital_id', 'barcode']);
        });

        Schema::create('pharmacy_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sale_number', 50);
            $table->decimal('total', 12, 2)->default(0);
            $table->dateTime('sold_at');
            $table->timestamps();

            $table->unique(['hospital_id', 'sale_number']);
            $table->index(['hospital_id', 'sold_at']);
        });

        Schema::create('pharmacy_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pharmacy_sale_id')->constrained('pharmacy_sales')->cascadeOnDelete();
            $table->foreignId('medication_id')->nullable()->constrained('medications')->nullOnDelete();
            $table->string('name');
            $table->string('barcode', 100)->nullable();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_sale_items');
        Schema::dropIfExists('pharmacy_sales');

        Schema::table('medications', function (Blueprint $table): void {
            $table->dropIndex(['hospital_id', 'barcode']);
            $table->dropConstrainedForeignId('hospital_id');
            $table->dropColumn('quantity');
        });
    }
};
