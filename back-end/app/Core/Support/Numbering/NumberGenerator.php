<?php

declare(strict_types=1);

namespace App\Core\Support\Numbering;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Produces the human readable identifiers the hospital staff actually use
 * (MRN-2026-000154, ENC-2026-00981, LAB-2026-00125, ...).
 *
 * Counters live in the `number_sequences` table and are incremented under a
 * row lock, so two receptionists registering a patient at the same moment can
 * never be handed the same MRN.
 */
final class NumberGenerator
{
    public function mrn(int $hospitalId): string
    {
        return $this->daily(SequenceKey::Mrn, $hospitalId);
    }

    public function encounterNumber(int $hospitalId): string
    {
        return $this->daily(SequenceKey::Encounter, $hospitalId);
    }

    public function orderNumber(int $hospitalId): string
    {
        return $this->next(SequenceKey::Order, $hospitalId);
    }

    public function sampleNumber(int $hospitalId): string
    {
        return $this->next(SequenceKey::LabSample, $hospitalId);
    }

    public function studyNumber(int $hospitalId): string
    {
        return $this->next(SequenceKey::RadiologyStudy, $hospitalId);
    }

    public function prescriptionNumber(int $hospitalId): string
    {
        return $this->next(SequenceKey::Prescription, $hospitalId);
    }

    public function dispenseNumber(int $hospitalId): string
    {
        return $this->next(SequenceKey::Dispense, $hospitalId);
    }

    public function admissionNumber(int $hospitalId): string
    {
        return $this->next(SequenceKey::Admission, $hospitalId);
    }

    public function invoiceNumber(int $hospitalId): string
    {
        return $this->next(SequenceKey::Invoice, $hospitalId);
    }

    public function receiptNumber(int $hospitalId): string
    {
        return $this->next(SequenceKey::Receipt, $hospitalId);
    }

    public function appointmentNumber(int $hospitalId, ?\DateTimeInterface $day = null): string
    {
        return $this->daily(SequenceKey::Appointment, $hospitalId, $day);
    }

    public function operationNumber(int $hospitalId): string
    {
        return $this->next(SequenceKey::Operation, $hospitalId);
    }

    public function employeeNumber(int $hospitalId): string
    {
        return $this->next(SequenceKey::Employee, $hospitalId);
    }

    public function next(SequenceKey $key, int $hospitalId): string
    {
        $period = (string) now()->year;
        $value = $this->increment($key, $hospitalId, $period);

        return sprintf(
            '%s-%s-%s',
            $key->prefix(),
            $period,
            str_pad((string) $value, $key->padding(), '0', STR_PAD_LEFT),
        );
    }

    private function daily(SequenceKey $key, int $hospitalId, ?\DateTimeInterface $day = null): string
    {
        $period = Carbon::parse($day ?? now())->toDateString();

        return $period.'-'.$this->increment($key, $hospitalId, $period);
    }

    private function increment(SequenceKey $key, int $hospitalId, string $period): int
    {
        return DB::transaction(function () use ($key, $hospitalId, $period): int {
            DB::table('number_sequences')->insertOrIgnore([
                'hospital_id' => $hospitalId,
                'sequence_key' => $key->value,
                'period' => $period,
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('number_sequences')
                ->where('hospital_id', $hospitalId)
                ->where('sequence_key', $key->value)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            $next = (int) $row->current_value + 1;

            DB::table('number_sequences')
                ->where('id', $row->id)
                ->update(['current_value' => $next, 'updated_at' => now()]);

            return $next;
        });
    }
}
