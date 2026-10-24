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
        if (! Schema::hasTable('blood_products')) {
            Schema::create('blood_products', function (Blueprint $table): void {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->id();
                $table->string('name');
                $table->string('code', 50)->unique();
                $table->string('product_type', 30);
                $table->decimal('price', 12, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['product_type', 'is_active']);
            });

            $now = now();

            DB::table('blood_products')->insert([
                [
                    'name' => 'كريات مكثفة',
                    'code' => 'packed_rbc',
                    'product_type' => 'packed_rbc',
                    'price' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'بلازما',
                    'code' => 'plasma',
                    'product_type' => 'plasma',
                    'price' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'صفيحات',
                    'code' => 'platelets',
                    'product_type' => 'platelets',
                    'price' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        if (! Schema::hasTable('blood_requests')) {
            return;
        }

        $missingProduct = ! Schema::hasColumn('blood_requests', 'blood_product_id');
        $missingName = ! Schema::hasColumn('blood_requests', 'product_name');
        $missingPrice = ! Schema::hasColumn('blood_requests', 'unit_price');

        if (! $missingProduct && ! $missingName && ! $missingPrice) {
            return;
        }

        Schema::table('blood_requests', function (Blueprint $table) use ($missingProduct, $missingName, $missingPrice): void {
            if ($missingProduct) {
                $table->foreignId('blood_product_id')->nullable()->after('encounter_id')->constrained('blood_products')->nullOnDelete();
            }
            if ($missingName) {
                $table->string('product_name')->nullable()->after('product_type');
            }
            if ($missingPrice) {
                $table->decimal('unit_price', 12, 2)->default(0)->after('units');
            }
        });
    }

    public function down(): void
    {
        Schema::table('blood_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('blood_product_id');
            $table->dropColumn(['product_name', 'unit_price']);
        });

        Schema::dropIfExists('blood_products');
    }
};
