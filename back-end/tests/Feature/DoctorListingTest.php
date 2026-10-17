<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class DoctorListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_doctor_without_a_clinic_appears_for_that_departments_clinic(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        $admin = UserModel::factory()->withRole('admin')->create();
        Sanctum::actingAs($admin);

        $clinic = ClinicModel::query()->where('code', 'EN-GEN')->firstOrFail();
        $other = ClinicModel::query()->where('code', 'DN-GEN')->firstOrFail();
        $hospitalId = HospitalModel::query()->value('id');

        $this->postJson('/api/staff', [
            'hospital_id' => $hospitalId,
            'first_name' => 'هاني',
            'last_name' => 'الأنف',
            'department_id' => $clinic->department_id,
            'role' => 'doctor',
            'username' => 'ent.doc',
            'password' => 'secret1',
        ])->assertCreated();

        $this->postJson('/api/staff', [
            'hospital_id' => $hospitalId,
            'first_name' => 'سامر',
            'last_name' => 'الأسنان',
            'department_id' => $other->department_id,
            'clinic_id' => $other->getKey(),
            'role' => 'doctor',
            'username' => 'dent.doc',
            'password' => 'secret1',
        ])->assertCreated();

        $names = collect($this->getJson('/api/doctors?clinic_id='.$clinic->getKey())->assertOk()->json('data'))
            ->pluck('name');

        $this->assertTrue($names->contains('هاني الأنف'));
        $this->assertFalse($names->contains('سامر الأسنان'));
    }

    public function test_staff_in_a_single_clinic_department_keeps_that_clinic(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        $admin = UserModel::factory()->withRole('admin')->create();
        Sanctum::actingAs($admin);

        $clinic = ClinicModel::query()->where('code', 'DN-GEN')->firstOrFail();
        $hospitalId = HospitalModel::query()->value('id');

        $created = $this->postJson('/api/staff', [
            'hospital_id' => $hospitalId,
            'first_name' => 'محمد',
            'last_name' => 'المحمد',
            'employee_number' => '001',
            'department_id' => $clinic->department_id,
            'role' => 'doctor',
            'username' => 'tooth.doc',
            'password' => 'secret1',
        ])->assertCreated();

        $created->assertJsonPath('data.clinic.id', $clinic->getKey());
        $created->assertJsonPath('data.clinic.name', 'عيادة الأسنان');

        $staffId = $created->json('data.id');

        $this->patchJson("/api/staff/{$staffId}", [
            'first_name' => 'محمد',
            'department_id' => $clinic->department_id,
            'clinic_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.clinic.id', $clinic->getKey());

        $this->getJson('/api/staff')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $clinic->getKey(),
                'name' => 'عيادة الأسنان',
            ]);
    }
}
