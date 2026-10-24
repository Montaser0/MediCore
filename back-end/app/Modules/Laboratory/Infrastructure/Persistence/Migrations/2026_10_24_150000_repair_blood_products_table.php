<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Heals servers where the first blood-product migration created the table
 * and then stopped, leaving product_type required and the catalogue empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blood_products')) {
            Schema::create('blood_products', function (Blueprint $table): void {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->id();
                $table->string('name');
                $table->string('code', 50)->unique();
                $table->decimal('price', 12, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('blood_products', 'product_type')) {
            if (Schema::hasIndex('blood_products', ['product_type', 'is_active'])) {
                Schema::table('blood_products', function (Blueprint $table): void {
                    $table->dropIndex(['product_type', 'is_active']);
                });
            }

            Schema::table('blood_products', function (Blueprint $table): void {
                $table->dropColumn('product_type');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE blood_products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }

        if (DB::table('blood_products')->count() === 0) {
            $now = now();

            DB::table('blood_products')->insert([
                [
                    'name' => 'كريات مكثفة',
                    'code' => 'packed_rbc',
                    'price' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'بلازما',
                    'code' => 'plasma',
                    'price' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'صفيحات',
                    'code' => 'platelets',
                    'price' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        // The catalogue no longer stores a product type.
    }
};
