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
}
