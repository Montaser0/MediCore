<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drug_catalogue_items', function (Blueprint $table): void {
            $table->decimal('official_price', 12, 2)->nullable()->after('barcode');
            $table->text('composition')->nullable()->after('generic_name');
            $table->string('bulletin_label', 120)->nullable()->after('published_category');
            $table->timestamp('bulletin_updated_at')->nullable()->after('bulletin_label');
        });

        Schema::table('medications', function (Blueprint $table): void {
            $table->decimal('reorder_level', 12, 2)->default(0)->after('quantity');
        });

        Schema::table('pharmacy_sale_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 12, 2)->default(0)->after('unit_price');
            $table->decimal('cost_total', 12, 2)->default(0)->after('line_total');
        });

        Schema::create('pharmacy_cash_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->decimal('opening_float', 12, 2)->default(0);
            $table->dateTime('closed_at')->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_cash_sessions');

        Schema::table('pharmacy_sale_items', function (Blueprint $table): void {
            $table->dropColumn(['unit_cost', 'cost_total']);
        });

        Schema::table('medications', function (Blueprint $table): void {
            $table->dropColumn('reorder_level');
        });

        Schema::table('drug_catalogue_items', function (Blueprint $table): void {
            $table->dropColumn(['official_price', 'composition', 'bulletin_label', 'bulletin_updated_at']);
        });
    }
};
