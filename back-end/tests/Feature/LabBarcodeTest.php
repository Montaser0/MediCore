<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\DoctorModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Laboratory\Application\Actions\CreateLabOrderAction;
use App\Modules\Laboratory\Application\Support\LabBarcode;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabOrderModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabTestModel;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class LabBarcodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // This machine may be running the pharmacy edition. The slip belongs to the laboratory module.
        putenv('HMS_EDITION=hospital');
        $_ENV['HMS_EDITION'] = 'hospital';
        $_SERVER['HMS_EDITION'] = 'hospital';
        parent::setUp();
    }

    public function test_a_lab_request_gets_a_barcode_the_laboratory_can_scan(): void
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
            'code' => 'INT-BC',
            'type' => 'medical',
        ]);
        $clinic = ClinicModel::query()->create([
            'department_id' => $department->getKey(),
            'name' => 'عيادة القلب',
            'code' => 'CARD-BC',
        ]);
        $staff = StaffModel::factory()->create([
            'hospital_id' => $hospitalId,
            'first_name' => 'سامر',
            'last_name' => 'حداد',
            'job_title' => 'طبيب اختصاصي',
        ]);
        $doctor = DoctorModel::query()->create([
            'staff_id' => $staff->getKey(),
            'specialty' => 'قلبية',
        ]);
        $encounter = EncounterModel::query()->create([
            'hospital_id' => $hospitalId,
            'patient_id' => $patient->getKey(),
            'encounter_number' => 'ENC-BC-1',
            'type' => 'outpatient',
            'status' => 'in_progress',
            'department_id' => $department->getKey(),
            'clinic_id' => $clinic->getKey(),
            'doctor_id' => $doctor->getKey(),
            'started_at' => now(),
        ]);
        $order = OrderModel::query()->create([
            'hospital_id' => $hospitalId,
            'encounter_id' => $encounter->getKey(),
            'order_number' => 'ORD-BC-1',
            'type' => 'laboratory',
            'status' => 'pending',
            'priority' => 'routine',
            'ordered_by' => $admin->getKey(),
            'ordered_at' => now(),
        ]);
        $cbc = LabTestModel::query()->create([
            'name' => 'تعداد دم',
            'code' => 'CBC-BC',
            'price' => 5,
        ]);
        $glucose = LabTestModel::query()->create([
            'name' => 'سكر صيامي',
            'code' => 'GLU-BC',
            'price' => 7,
        ]);

        app(CreateLabOrderAction::class)->fulfil($order->getKey(), [
            ['service_id' => $cbc->getKey(), 'name' => $cbc->name],
            ['service_id' => $glucose->getKey(), 'name' => $glucose->name],
        ]);

        $labOrder = LabOrderModel::query()->where('order_id', $order->getKey())->firstOrFail();
        $barcode = (string) $labOrder->barcode;

        $this->assertTrue(LabBarcode::valid($barcode));
        $this->assertStringStartsWith('23', $barcode);
        $this->assertSame('عيادة القلب', $labOrder->clinic_name);
        $this->assertSame('سامر حداد', $labOrder->doctor_name);
        $this->assertNotSame($barcode, LabBarcode::next());

        Sanctum::actingAs($admin);

        $this->getJson('/api/laboratory/orders/barcode/'.$barcode)
            ->assertOk()
            ->assertJsonPath('data.barcode', $barcode)
            ->assertJsonPath('data.clinic', 'عيادة القلب')
            ->assertJsonPath('data.doctor', 'سامر حداد')
            ->assertJsonPath('data.patient.full_name', 'ليلى أحمد حسن عمر')
            ->assertJsonCount(2, 'data.tests');

        $this->getJson('/api/laboratory/orders/barcode/2300000000005')
            ->assertNotFound();
    }
}
