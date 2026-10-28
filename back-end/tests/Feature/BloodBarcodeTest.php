<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Laboratory\Application\Actions\CreateBloodRequestAction;
use App\Modules\Laboratory\Application\Support\LabBarcode;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodProductModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class BloodBarcodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        putenv('HMS_EDITION=hospital');
        $_ENV['HMS_EDITION'] = 'hospital';
        $_SERVER['HMS_EDITION'] = 'hospital';
        parent::setUp();
    }

    public function test_a_blood_request_gets_a_barcode_the_laboratory_can_scan(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class, AdminUserSeeder::class]);
        $admin = UserModel::query()->where('username', 'admin')->firstOrFail();
        $hospitalId = (int) $admin->staff()->value('hospital_id');

        $patient = PatientModel::factory()->create([
            'hospital_id' => $hospitalId,
            'first_name' => 'ليلى',
            'father_name' => 'أحمد',
            'grandfather_name' => 'حسن',
            'last_name' => 'عمر',
        ]);
        $department = DepartmentModel::query()->create([
            'hospital_id' => $hospitalId,
            'name' => 'الباطنة',
            'code' => 'INT-BL',
            'type' => 'medical',
        ]);
        $clinic = ClinicModel::query()->create([
            'department_id' => $department->getKey(),
            'name' => 'عيادة القلب',
            'code' => 'CARD-BL',
        ]);
        $encounter = EncounterModel::query()->create([
            'hospital_id' => $hospitalId,
            'patient_id' => $patient->getKey(),
            'encounter_number' => 'ENC-BL-1',
            'type' => 'outpatient',
            'status' => 'in_progress',
            'department_id' => $department->getKey(),
            'clinic_id' => $clinic->getKey(),
            'started_at' => now(),
        ]);
        $product = BloodProductModel::query()->where('code', 'packed_rbc')->firstOrFail();

        Sanctum::actingAs($admin);

        $request = app(CreateBloodRequestAction::class)->execute([
            'patient_id' => $patient->getKey(),
            'encounter_id' => $encounter->getKey(),
            'blood_product_id' => $product->getKey(),
            'blood_group' => 'A+',
            'units' => 2,
        ]);

        $barcode = (string) $request->barcode;

        $this->assertTrue(LabBarcode::valid($barcode));
        $this->assertStringStartsWith('24', $barcode);
        $this->assertSame('عيادة القلب', $request->clinic_name);

        $this->getJson('/api/laboratory/blood-requests/barcode/'.$barcode)
            ->assertOk()
            ->assertJsonPath('data.barcode', $barcode)
            ->assertJsonPath('data.clinic_name', 'عيادة القلب')
            ->assertJsonPath('data.patient.full_name', 'ليلى أحمد حسن عمر')
            ->assertJsonPath('data.blood_group', 'A+')
            ->assertJsonPath('data.units', 2);

        $this->getJson('/api/laboratory/blood-requests/barcode/2400000000004')
            ->assertNotFound();

        $this->getJson('/api/laboratory/blood-requests?search='.$barcode)
            ->assertOk()
            ->assertJsonPath('data.0.barcode', $barcode);

        $this->getJson('/api/laboratory/blood-requests?search=2400000099991')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
