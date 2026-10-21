<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A doctor examines patients in one clinic. Registration then lists only
 * the doctors of the clinic the receptionist picked.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('doctors', 'clinic_id')) {
            return;
        }

        Schema::table('doctors', function (Blueprint $table): void {
            $table->foreignId('clinic_id')
                ->nullable()
                ->after('staff_id')
                ->constrained('clinics')
                ->nullOnDelete();
        });

        $departmentIds = DB::table('staff')
            ->whereNotNull('department_id')
            ->distinct()
            ->pluck('department_id');

        foreach ($departmentIds as $departmentId) {
            $clinicIds = DB::table('clinics')
                ->where('department_id', $departmentId)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if ($clinicIds === []) {
                continue;
            }

            $doctorIds = DB::table('doctors')
                ->join('staff', 'staff.id', '=', 'doctors.staff_id')
                ->where('staff.department_id', $departmentId)
                ->orderBy('doctors.id')
                ->pluck('doctors.id');

            foreach ($doctorIds as $index => $doctorId) {
                DB::table('doctors')->where('id', $doctorId)->update([
                    'clinic_id' => $clinicIds[$index % count($clinicIds)],
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('clinic_id');
        });
    }
};
