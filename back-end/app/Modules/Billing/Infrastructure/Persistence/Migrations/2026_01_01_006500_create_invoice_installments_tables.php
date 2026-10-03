<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_installment_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained('invoices')->cascadeOnDelete();
            // Paid amount already on the invoice when the plan was opened.
            // Later receipts are what fill the installments, not earlier ones.
            $table->decimal('baseline_paid', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('invoice_installment_plans')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();

            $table->unique(['plan_id', 'sequence']);
            $table->index(['invoice_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_installments');
        Schema::dropIfExists('invoice_installment_plans');
    }
};
