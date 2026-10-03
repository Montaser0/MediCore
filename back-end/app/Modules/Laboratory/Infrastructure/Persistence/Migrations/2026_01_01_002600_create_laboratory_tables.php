<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_tests', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('category', 150)->nullable();
            $table->string('sample_type', 100)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('reference_range')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedSmallInteger('turnaround_hours')->nullable();
            $table->boolean('is_panel')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        // A panel such as a CBC reports many values; each one is a parameter
        // with its own reference and critical thresholds.
        Schema::create('lab_test_parameters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lab_test_id')->constrained('lab_tests')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('reference_range')->nullable();
            $table->decimal('normal_low', 12, 4)->nullable();
            $table->decimal('normal_high', 12, 4)->nullable();
            $table->decimal('critical_low', 12, 4)->nullable();
            $table->decimal('critical_high', 12, 4)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['lab_test_id', 'display_order']);
        });

        // The laboratory's own view of an order from the order engine.
        Schema::create('lab_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('lab_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained('lab_tests');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->enum('status', [
                'pending', 'sample_collected', 'processing',
                'result_ready', 'verified', 'cancelled',
            ])->default('pending');
            $table->timestamps();

            $table->index(['lab_order_id', 'status']);
            $table->index('status');
        });

        Schema::create('lab_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lab_order_item_id')->constrained('lab_order_items')->cascadeOnDelete();
            $table->string('sample_number', 50)->unique();
            $table->string('sample_type', 100)->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('collected_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->enum('status', ['pending', 'collected', 'received', 'processing', 'rejected'])
                ->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('lab_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lab_order_item_id')->unique()->constrained('lab_order_items')->cascadeOnDelete();
            $table->text('result_value')->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('reference_range')->nullable();
            $table->enum('flag', ['normal', 'low', 'high', 'critical'])->default('normal');
            $table->text('comment')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('entered_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->timestamps();

            $table->index(['status', 'flag']);
        });

        Schema::create('lab_result_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lab_result_id')->constrained('lab_results')->cascadeOnDelete();
            $table->foreignId('lab_test_parameter_id')->constrained('lab_test_parameters');
            $table->string('value')->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('reference_range')->nullable();
            $table->enum('flag', ['normal', 'low', 'high', 'critical'])->default('normal');
            $table->timestamps();

            $table->unique(['lab_result_id', 'lab_test_parameter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_result_values');
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('lab_samples');
        Schema::dropIfExists('lab_order_items');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_test_parameters');
        Schema::dropIfExists('lab_tests');
    }
};
