<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A clinic lists its own doctors. Departments with more clinics than doctors
 * still get one doctor on every clinic, so registration never shows an empty list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_doctor', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->unique(['clinic_id', 'doctor_id'], 'uq_clinic_doctor');
        });

        $assigned = DB::table('doctors')->whereNotNull('clinic_id')->get(['id', 'clinic_id']);
        foreach ($assigned as $doctor) {
            DB::table('clinic_doctor')->insert([
                'clinic_id' => $doctor->clinic_id,
                'doctor_id' => $doctor->id,
            ]);
        }

        $departmentIds = DB::table('clinics')->distinct()->pluck('department_id');
        foreach ($departmentIds as $departmentId) {
            $clinicIds = DB::table('clinics')
                ->where('department_id', $departmentId)
                ->orderBy('id')
                ->pluck('id')
                ->all();
            $doctorIds = DB::table('doctors')
                ->join('staff', 'staff.id', '=', 'doctors.staff_id')
                ->where('staff.department_id', $departmentId)
                ->orderBy('doctors.id')
                ->pluck('doctors.id')
                ->all();

            if ($clinicIds === [] || $doctorIds === []) {
                continue;
            }

            foreach ($clinicIds as $index => $clinicId) {
                $doctorId = $doctorIds[$index % count($doctorIds)];
                $exists = DB::table('clinic_doctor')
                    ->where('clinic_id', $clinicId)
                    ->where('doctor_id', $doctorId)
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('clinic_doctor')->insert([
                    'clinic_id' => $clinicId,
                    'doctor_id' => $doctorId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_doctor');
    }
};
