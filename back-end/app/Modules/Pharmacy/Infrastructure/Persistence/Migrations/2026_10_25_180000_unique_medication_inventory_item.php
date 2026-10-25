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
        $duplicates = DB::table('medications')
            ->select('inventory_item_id')
            ->whereNotNull('inventory_item_id')
            ->groupBy('inventory_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('inventory_item_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'لا يمكن ربط أكثر من دواء بصنف المخزون نفسه: '.$duplicates->implode(', '),
            );
        }

        Schema::table('medications', function (Blueprint $table): void {
            $table->unique('inventory_item_id', 'medications_inventory_item_unique');
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table): void {
            $table->dropUnique('medications_inventory_item_unique');
        });
    }
};
