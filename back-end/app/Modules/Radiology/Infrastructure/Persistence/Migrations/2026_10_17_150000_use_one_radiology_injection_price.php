<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('radiology_services', 'injection_price')) {
            Schema::table('radiology_services', function (Blueprint $table): void {
                $table->dropColumn('injection_price');
            });
        }

        if (! Schema::hasColumn('radiology_orders', 'injection_price')) {
            Schema::table('radiology_orders', function (Blueprint $table): void {
                $table->decimal('injection_price', 12, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('radiology_orders', 'injection_price')) {
            Schema::table('radiology_orders', function (Blueprint $table): void {
                $table->dropColumn('injection_price');
            });
        }

        if (! Schema::hasColumn('radiology_services', 'injection_price')) {
            Schema::table('radiology_services', function (Blueprint $table): void {
                $table->decimal('injection_price', 12, 2)->default(0);
            });
        }
    }
};
