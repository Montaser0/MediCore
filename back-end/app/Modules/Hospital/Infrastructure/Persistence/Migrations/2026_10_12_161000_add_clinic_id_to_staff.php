<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('staff', 'clinic_id')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table): void {
            $table->foreignId('clinic_id')->nullable()->after('department_id')->constrained('clinics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('clinic_id');
        });
    }
};
