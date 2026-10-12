<?php

declare(strict_types=1);

use App\Core\Support\Numbering\DailyNumber;
use App\Core\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes that match the filters the desks actually run: file/visit number,
 * phone, department + status + date, and invoice status + date.
 * Wider composites replace the shorter indexes they cover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->unsignedInteger('mrn_serial')->nullable()->after('mrn');
            $table->string('name_en', 200)->nullable()->after('last_name');
            $table->string('phone_normalized', 20)->nullable()->after('phone');
            $table->string('phone_tail', 9)->nullable()->after('phone_normalized');
            $table->string('phone_secondary_normalized', 20)->nullable()->after('phone_secondary');
            $table->string('phone_secondary_tail', 9)->nullable()->after('phone_secondary_normalized');
        });

        Schema::table('encounters', function (Blueprint $table): void {
            $table->unsignedInteger('encounter_serial')->nullable()->after('encounter_number');
        });

        $this->backfillPatients();
        $this->backfillEncounters();

        Schema::table('patients', function (Blueprint $table): void {
            $table->index(['hospital_id', 'mrn_serial'], 'idx_patients_hospital_mrn_serial');
            $table->index(['hospital_id', 'phone_tail'], 'idx_patients_hospital_phone_tail');
            $table->index(['hospital_id', 'phone_secondary_tail'], 'idx_patients_hospital_phone2_tail');
            $table->index(['hospital_id', 'phone_normalized'], 'idx_patients_hospital_phone');
            $table->index(['hospital_id', 'created_at'], 'idx_patients_hospital_created');
            $table->index('first_name', 'idx_patients_first_name');
            $table->index('name_en', 'idx_patients_name_en');
        });

        Schema::table('encounters', function (Blueprint $table): void {
            $table->index(['hospital_id', 'encounter_serial'], 'idx_encounters_hospital_serial');
            $table->index(['hospital_id', 'created_at'], 'idx_encounters_hospital_created');
            $table->index(['department_id', 'status', 'created_at'], 'idx_encounters_dept_status_created');
            $table->index(['clinic_id', 'status', 'created_at'], 'idx_encounters_clinic_status_created');
            $table->index(['doctor_id', 'status', 'created_at'], 'idx_encounters_doctor_status_created');
            $table->index(['patient_id', 'status', 'created_at'], 'idx_encounters_patient_status_created');
        });

        $this->dropIndexIfExists('encounters', 'idx_encounter_department_queue');
        $this->dropIndexIfExists('encounters', 'idx_encounter_doctor_queue');
        $this->dropIndexIfExists('encounters', 'idx_encounter_status');
        $this->dropIndexIfExists('encounters', 'idx_encounter_date');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['hospital_id', 'status', 'created_at'], 'idx_invoices_hospital_status_created');
            $table->index(['patient_id', 'status', 'created_at'], 'idx_invoices_patient_status_created');
        });

        $this->dropIndexIfExists('invoices', 'invoices_patient_id_status_index');
        $this->dropIndexIfExists('invoices', 'invoices_status_index');

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['hospital_id', 'ordered_at'], 'idx_orders_hospital_ordered');
        });

        Schema::table('lab_order_items', function (Blueprint $table): void {
            $table->index(['status', 'id'], 'idx_lab_items_status_id');
        });
        $this->dropIndexIfExists('lab_order_items', 'lab_order_items_status_index');

        Schema::table('radiology_order_items', function (Blueprint $table): void {
            $table->index(['status', 'id'], 'idx_rad_items_status_id');
        });
        $this->dropIndexIfExists('radiology_order_items', 'radiology_order_items_status_index');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->index(['department_id', 'status', 'scheduled_at'], 'idx_appointments_dept_status_at');
            $table->index(['clinic_id', 'status', 'scheduled_at'], 'idx_appointments_clinic_status_at');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['hospital_id', 'paid_at'], 'idx_payments_hospital_paid');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('payments', 'idx_payments_hospital_paid');
        $this->dropIndexIfExists('appointments', 'idx_appointments_clinic_status_at');
        $this->dropIndexIfExists('appointments', 'idx_appointments_dept_status_at');
        $this->dropIndexIfExists('radiology_order_items', 'idx_rad_items_status_id');
        $this->dropIndexIfExists('lab_order_items', 'idx_lab_items_status_id');
        $this->dropIndexIfExists('orders', 'idx_orders_hospital_ordered');
        $this->dropIndexIfExists('invoices', 'idx_invoices_patient_status_created');
        $this->dropIndexIfExists('invoices', 'idx_invoices_hospital_status_created');
        $this->dropIndexIfExists('encounters', 'idx_encounters_patient_status_created');
        $this->dropIndexIfExists('encounters', 'idx_encounters_doctor_status_created');
        $this->dropIndexIfExists('encounters', 'idx_encounters_clinic_status_created');
        $this->dropIndexIfExists('encounters', 'idx_encounters_dept_status_created');
        $this->dropIndexIfExists('encounters', 'idx_encounters_hospital_created');
        $this->dropIndexIfExists('encounters', 'idx_encounters_hospital_serial');
        $this->dropIndexIfExists('patients', 'idx_patients_name_en');
        $this->dropIndexIfExists('patients', 'idx_patients_first_name');
        $this->dropIndexIfExists('patients', 'idx_patients_hospital_created');
        $this->dropIndexIfExists('patients', 'idx_patients_hospital_phone');
        $this->dropIndexIfExists('patients', 'idx_patients_hospital_phone2_tail');
        $this->dropIndexIfExists('patients', 'idx_patients_hospital_phone_tail');
        $this->dropIndexIfExists('patients', 'idx_patients_hospital_mrn_serial');

        Schema::table('encounters', function (Blueprint $table): void {
            $table->dropColumn('encounter_serial');
            $table->index('status', 'idx_encounter_status');
            $table->index('created_at', 'idx_encounter_date');
            $table->index(['department_id', 'status'], 'idx_encounter_department_queue');
            $table->index(['doctor_id', 'status'], 'idx_encounter_doctor_queue');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['patient_id', 'status']);
            $table->index('status');
        });

        Schema::table('lab_order_items', function (Blueprint $table): void {
            $table->index('status');
        });

        Schema::table('radiology_order_items', function (Blueprint $table): void {
            $table->index('status');
        });

        Schema::table('patients', function (Blueprint $table): void {
            $table->dropColumn([
                'mrn_serial',
                'name_en',
                'phone_normalized',
                'phone_tail',
                'phone_secondary_normalized',
                'phone_secondary_tail',
            ]);
        });
    }

    private function backfillPatients(): void
    {
        DB::table('patients')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('patients')->where('id', $row->id)->update([
                    'mrn_serial' => DailyNumber::serial($row->mrn),
                    'phone_normalized' => PhoneNumber::normalize($row->phone),
                    'phone_tail' => PhoneNumber::tail($row->phone),
                    'phone_secondary_normalized' => PhoneNumber::normalize($row->phone_secondary),
                    'phone_secondary_tail' => PhoneNumber::tail($row->phone_secondary),
                ]);
            }
        });
    }

    private function backfillEncounters(): void
    {
        DB::table('encounters')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('encounters')->where('id', $row->id)->update([
                    'encounter_serial' => DailyNumber::serial($row->encounter_number),
                ]);
            }
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (Schema::hasIndex($table, $index)) {
            Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                $blueprint->dropIndex($index);
            });
        }
    }
};
