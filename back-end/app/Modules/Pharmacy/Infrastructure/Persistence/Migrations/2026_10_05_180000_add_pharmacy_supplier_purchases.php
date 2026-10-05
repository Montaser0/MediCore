<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'name'], 'pharmacy_supplier_name');
        });

        Schema::create('pharmacy_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('pharmacy_supplier_id')->constrained('pharmacy_suppliers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_number');
            $table->decimal('total', 12, 2);
            $table->decimal('cash_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['hospital_id', 'invoice_number'], 'pharmacy_purchase_invoice');
            $table->index(['hospital_id', 'received_at'], 'pharmacy_purchase_received');
        });

        Schema::create('pharmacy_supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('pharmacy_supplier_id')->constrained('pharmacy_suppliers')->cascadeOnDelete();
            $table->foreignId('pharmacy_purchase_id')->constrained('pharmacy_purchases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['hospital_id', 'paid_at'], 'pharmacy_supplier_paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_supplier_payments');
        Schema::dropIfExists('pharmacy_purchases');
        Schema::dropIfExists('pharmacy_suppliers');
    }
};
