<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $table): void {
            $table->foreignId('service_id')
                ->nullable()
                ->after('doctor_id')
                ->constrained('services')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_id');
        });
    }
};
