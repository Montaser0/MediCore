<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->string('receipt_number', 50);
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'card', 'bank_transfer', 'insurance', 'other']);
            $table->string('reference', 100)->nullable();
            $table->foreignId('received_by')->constrained('users');
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'receipt_number']);
            $table->index(['invoice_id', 'paid_at']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments');
            $table->decimal('amount', 12, 2);
            $table->string('reason', 500);
            $table->foreignId('refunded_by')->constrained('users');
            $table->dateTime('refunded_at');
            $table->timestamps();

            $table->index('payment_id');
        });

        Schema::create('insurance_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->decimal('default_coverage_percentage', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('patient_insurance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('insurance_company_id')->constrained('insurance_companies');
            $table->string('policy_number', 100);
            $table->string('member_number', 100)->nullable();
            $table->decimal('coverage_percentage', 5, 2)->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['patient_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_insurance');
        Schema::dropIfExists('insurance_companies');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
    }
};
