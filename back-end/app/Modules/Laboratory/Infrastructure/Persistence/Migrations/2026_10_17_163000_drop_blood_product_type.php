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
        if (
            Schema::hasTable('blood_requests')
            && Schema::hasColumn('blood_requests', 'product_type')
            && Schema::hasColumn('blood_requests', 'product_name')
        ) {
            $names = [
                'packed_rbc' => 'كريات مكثفة',
                'plasma' => 'بلازما',
                'platelets' => 'صفيحات',
            ];

            foreach ($names as $type => $name) {
                DB::table('blood_requests')
                    ->where('product_type', $type)
                    ->where(function ($query): void {
                        $query->whereNull('product_name')->orWhere('product_name', '');
                    })
                    ->update(['product_name' => $name]);
            }
        }

        if (! Schema::hasTable('blood_products') || ! Schema::hasColumn('blood_products', 'product_type')) {
            return;
        }

        if (Schema::hasIndex('blood_products', ['product_type', 'is_active'])) {
            Schema::table('blood_products', function (Blueprint $table): void {
                $table->dropIndex(['product_type', 'is_active']);
            });
        }

        Schema::table('blood_products', function (Blueprint $table): void {
            $table->dropColumn('product_type');
        });
    }

    public function down(): void
    {
        Schema::table('blood_products', function (Blueprint $table): void {
            $table->string('product_type', 30)->nullable()->after('code');
            $table->index(['product_type', 'is_active']);
        });
    }
};
