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
            $table->unsignedSmallInteger('strips_per_box')->default(1)->after('reorder_level');
            $table->unsignedSmallInteger('tablets_per_strip')->default(1)->after('strips_per_box');
            $table->decimal('price_strip', 12, 2)->default(0)->after('price');
            $table->decimal('price_tablet', 12, 2)->default(0)->after('price_strip');
        });

        Schema::table('pharmacy_sale_items', function (Blueprint $table): void {
            $table->string('unit', 20)->default('علبة')->after('quantity');
            $table->decimal('base_quantity', 12, 2)->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('pharmacy_sale_items', function (Blueprint $table): void {
            $table->dropColumn(['unit', 'base_quantity']);
        });

        Schema::table('medications', function (Blueprint $table): void {
            $table->dropColumn(['strips_per_box', 'tablets_per_strip', 'price_strip', 'price_tablet']);
        });
    }
};
