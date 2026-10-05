<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'name'], 'pharmacy_customer_name');
        });

        Schema::table('pharmacy_sales', function (Blueprint $table): void {
            $table->foreignId('pharmacy_customer_id')->nullable()->after('user_id')->constrained('pharmacy_customers')->nullOnDelete();
            $table->decimal('cash_amount', 12, 2)->default(0)->after('total');
            $table->decimal('due_amount', 12, 2)->default(0)->after('cash_amount');
        });

        Schema::table('pharmacy_returns', function (Blueprint $table): void {
            $table->decimal('cash_refund', 12, 2)->default(0)->after('total');
        });

        Schema::create('pharmacy_debt_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('pharmacy_customer_id')->constrained('pharmacy_customers')->cascadeOnDelete();
            $table->foreignId('pharmacy_sale_id')->constrained('pharmacy_sales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['hospital_id', 'paid_at'], 'pharmacy_debt_paid_at');
        });

        DB::table('pharmacy_sales')->update([
            'cash_amount' => DB::raw('total'),
            'due_amount' => 0,
        ]);
        DB::table('pharmacy_returns')->update([
            'cash_refund' => DB::raw('total'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_debt_payments');
        Schema::table('pharmacy_returns', function (Blueprint $table): void {
            $table->dropColumn('cash_refund');
        });
        Schema::table('pharmacy_sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pharmacy_customer_id');
            $table->dropColumn(['cash_amount', 'due_amount']);
        });
        Schema::dropIfExists('pharmacy_customers');
    }
};
