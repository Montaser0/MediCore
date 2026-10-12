<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Support\Numbering\DailyNumber;
use App\Modules\Encounters\Application\Queries\ListEncountersQuery;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Patients\Application\Queries\SearchPatientsQuery;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PatientLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_matches_phone_file_number_and_name_prefix(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        $patient = PatientModel::factory()->create([
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'last_name' => 'الحسن',
            'phone' => '0991 234 567',
            'name_en' => 'Ahmad Hasan',
        ]);

        $this->assertTrue(PatientModel::query()->search('991234567')->whereKey($patient->getKey())->exists());
        $this->assertTrue(PatientModel::query()->search('+963991234567')->whereKey($patient->getKey())->exists());
        $this->assertTrue(PatientModel::query()->search('أحمد محمد')->whereKey($patient->getKey())->exists());
        $this->assertTrue(PatientModel::query()->search('Ahmad')->whereKey($patient->getKey())->exists());
        $this->assertTrue(PatientModel::query()->search('Ahmad Hasan')->whereKey($patient->getKey())->exists());
        $this->assertFalse(PatientModel::query()->search('حسن')->whereKey($patient->getKey())->exists());

        $serial = DailyNumber::serial($patient->getRawOriginal('mrn'));
        $this->assertNotNull($serial);
        $this->assertTrue(PatientModel::query()->search((string) $serial)->whereKey($patient->getKey())->exists());

        $listed = app(SearchPatientsQuery::class)->execute(['search' => 'Ahmad'], 25);
        $this->assertSame(1, $listed->total());

        EncounterModel::query()->create([
            'hospital_id' => $patient->hospital_id,
            'patient_id' => $patient->getKey(),
            'encounter_number' => '2026-10-12-7',
            'type' => 'outpatient',
            'status' => 'registered',
        ]);

        $this->assertTrue(PatientModel::query()->search('7')->whereKey($patient->getKey())->exists());

        $visits = app(ListEncountersQuery::class)->execute(['patient_id' => $patient->getKey()], 25);
        $this->assertSame(1, $visits->total());
        $this->assertNotNull($visits->items()[0]->patient);
    }
}
