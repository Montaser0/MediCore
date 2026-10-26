<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->dropForeign(['medication_id']);
        });

        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('medication_id')->nullable()->change();
            $table->string('medication_name')->nullable()->after('medication_id');
            $table->foreign('medication_id')->references('id')->on('medications');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->dropForeign(['medication_id']);
            $table->dropColumn('medication_name');
        });

        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('medication_id')->nullable(false)->change();
            $table->foreign('medication_id')->references('id')->on('medications');
        });
    }
};
