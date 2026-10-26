<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->dropForeign(['doctor_id']);
        });

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->unsignedBigInteger('doctor_id')->nullable()->change();
            $table->foreign('doctor_id')->references('id')->on('doctors');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->dropForeign(['doctor_id']);
        });

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->unsignedBigInteger('doctor_id')->nullable(false)->change();
            $table->foreign('doctor_id')->references('id')->on('doctors');
        });
    }
};
