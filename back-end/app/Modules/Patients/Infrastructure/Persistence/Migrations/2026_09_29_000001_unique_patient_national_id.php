<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A national id belongs to one medical file per hospital. Empty values stay
 * null so patients without an id are not treated as duplicates of each other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->unique(['hospital_id', 'national_id'], 'uq_patient_national_id');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropUnique('uq_patient_national_id');
        });
    }
};
