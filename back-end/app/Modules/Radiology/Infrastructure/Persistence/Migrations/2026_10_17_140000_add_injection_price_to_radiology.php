<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radiology_services', function (Blueprint $table): void {
            $table->decimal('injection_price', 12, 2)->default(0);
        });

        Schema::table('radiology_order_items', function (Blueprint $table): void {
            $table->boolean('with_injection')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('radiology_order_items', function (Blueprint $table): void {
            $table->dropColumn('with_injection');
        });

        Schema::table('radiology_services', function (Blueprint $table): void {
            $table->dropColumn('injection_price');
        });
    }
};
