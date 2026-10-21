<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Older databases were migrated before a doctor could belong to a clinic.
 * Saving must add the missing column instead of pretending the clinic was stored.
 */
final class EnsureStaffClinicSchema
{
    public function ensure(): void
    {
        $this->column('staff', function (Blueprint $table): void {
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
        });

        $this->column('doctors', function (Blueprint $table): void {
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
        });

        if (Schema::hasTable('clinic_doctor')) {
            return;
        }

        try {
            Schema::create('clinic_doctor', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
                $table->unique(['clinic_id', 'doctor_id'], 'uq_clinic_doctor');
            });
        } catch (Throwable) {
            if (! Schema::hasTable('clinic_doctor')) {
                throw BusinessRuleException::make('تعذر حفظ العيادة لأن جدول ربط الأطباء بالعيادات غير موجود.');
            }
        }
    }

    private function column(string $table, callable $definition): void
    {
        if (Schema::hasColumn($table, 'clinic_id')) {
            return;
        }

        try {
            Schema::table($table, $definition);
        } catch (Throwable) {
            if (! Schema::hasColumn($table, 'clinic_id')) {
                throw BusinessRuleException::make('تعذر حفظ العيادة لأن عمود العيادة غير موجود في قاعدة البيانات.');
            }
        }
    }
}
