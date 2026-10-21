<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Departments\Infrastructure\Persistence\Models\ClinicModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_chosen_clinic_stays_linked_to_the_doctor_after_saving_screens(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        $admin = UserModel::factory()->withRole('admin')->create();
        Sanctum::actingAs($admin);

        $clinic = ClinicModel::query()->where('code', 'IM-CARD')->firstOrFail();
        $hospitalId = HospitalModel::query()->value('id');

        $created = $this->postJson('/api/staff', [
            'hospital_id' => $hospitalId,
            'first_name' => 'ليلى',
            'last_name' => 'القلب',
            'department_id' => $clinic->department_id,
            'clinic_id' => $clinic->getKey(),
            'role' => 'doctor',
            'username' => 'heart.doc',
            'password' => 'secret1',
        ])->assertCreated();

        $staffId = $created->json('data.id');
        $doctorId = $created->json('data.doctor.id');
        $created->assertJsonPath('data.clinic.id', $clinic->getKey());

        $this->patchJson("/api/staff/{$staffId}", [
            'screens' => ['patients'],
        ])->assertOk()
            ->assertJsonPath('data.clinic.id', $clinic->getKey());

        $this->assertDatabaseHas('clinic_doctor', [
            'clinic_id' => $clinic->getKey(),
            'doctor_id' => $doctorId,
        ]);
    }

    public function test_staff_list_stays_available_when_clinic_roster_is_missing(): void
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
            'department_id' => $clinic->department_id,
            'role' => 'doctor',
            'username' => 'tooth.list',
            'password' => 'secret1',
        ])->assertCreated();

        $staffId = $created->json('data.id');
        DB::table('staff')->where('id', $staffId)->update(['clinic_id' => null]);
        DB::table('doctors')->where('staff_id', $staffId)->update(['clinic_id' => null]);
        Schema::drop('clinic_doctor');

        $this->getJson('/api/staff')
            ->assertOk()
            ->assertJsonFragment([
                'first_name' => 'محمد',
                'last_name' => 'المحمد',
            ]);
    }

    public function test_doctor_saved_on_one_clinic_is_listed_only_for_that_clinic(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        $admin = UserModel::factory()->withRole('admin')->create();
        Sanctum::actingAs($admin);

        $clinic = ClinicModel::query()->where('code', 'IM-CARD')->firstOrFail();
        $sibling = ClinicModel::query()->where('code', 'IM-GAST')->firstOrFail();
        $hospitalId = HospitalModel::query()->value('id');

        $created = $this->postJson('/api/staff', [
            'hospital_id' => $hospitalId,
            'first_name' => 'ليلى',
            'last_name' => 'القلب',
            'department_id' => $clinic->department_id,
            'clinic_id' => $clinic->getKey(),
            'role' => 'doctor',
            'username' => 'heart.list',
            'password' => 'secret1',
        ])->assertCreated()
            ->assertJsonPath('data.full_name', 'ليلى القلب');

        $this->assertNotNull($created->json('data.doctor.id'));
        $created->assertJsonPath('data.clinic.id', $clinic->getKey());

        $this->getJson('/api/doctors?clinic_id='.$clinic->getKey())
            ->assertOk()
            ->assertJsonFragment(['name' => 'ليلى القلب']);

        $siblingNames = collect($this->getJson('/api/doctors?clinic_id='.$sibling->getKey())->assertOk()->json('data'))
            ->pluck('name');
        $this->assertFalse($siblingNames->contains('ليلى القلب'));

        $this->getJson('/api/doctors?department_id='.$clinic->department_id.'&clinic_id='.$sibling->getKey())
            ->assertOk()
            ->assertJsonMissing(['name' => 'ليلى القلب']);
    }

    public function test_editing_a_doctor_saves_the_chosen_clinic(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        $admin = UserModel::factory()->withRole('admin')->create();
        Sanctum::actingAs($admin);

        $clinic = ClinicModel::query()->where('code', 'IM-CARD')->firstOrFail();
        $moved = ClinicModel::query()->where('code', 'IM-GEN')->firstOrFail();
        $hospitalId = HospitalModel::query()->value('id');

        $created = $this->postJson('/api/staff', [
            'hospital_id' => $hospitalId,
            'first_name' => 'ليلى',
            'last_name' => 'القلب',
            'department_id' => $clinic->department_id,
            'clinic_id' => $clinic->getKey(),
            'role' => 'doctor',
            'username' => 'heart.move',
            'password' => 'secret1',
        ])->assertCreated();

        $staffId = $created->json('data.id');

        $this->patchJson("/api/staff/{$staffId}", [
            'first_name' => 'ليلى',
            'department_id' => $moved->department_id,
            'clinic_id' => $moved->getKey(),
        ])->assertOk()
            ->assertJsonPath('data.clinic.id', $moved->getKey())
            ->assertJsonPath('data.clinic.name', 'عيادة الطب العام');

        $this->getJson('/api/staff')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $moved->getKey(),
                'name' => 'عيادة الطب العام',
            ]);

        $this->getJson('/api/doctors?clinic_id='.$moved->getKey())
            ->assertOk()
            ->assertJsonFragment(['name' => 'ليلى القلب']);

        $previous = collect($this->getJson('/api/doctors?clinic_id='.$clinic->getKey())->assertOk()->json('data'))
            ->pluck('name');
        $this->assertFalse($previous->contains('ليلى القلب'));
    }

    public function test_doctor_names_stay_available_when_clinic_roster_is_missing(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        $admin = UserModel::factory()->withRole('admin')->create();
        Sanctum::actingAs($admin);

        $clinic = ClinicModel::query()->where('code', 'IM-CARD')->firstOrFail();
        $hospitalId = HospitalModel::query()->value('id');

        $this->postJson('/api/staff', [
            'hospital_id' => $hospitalId,
            'first_name' => 'ليلى',
            'last_name' => 'القلب',
            'department_id' => $clinic->department_id,
            'clinic_id' => $clinic->getKey(),
            'role' => 'doctor',
            'username' => 'heart.roster',
            'password' => 'secret1',
        ])->assertCreated();

        Schema::drop('clinic_doctor');

        $this->getJson('/api/doctors?department_id='.$clinic->department_id)
            ->assertOk()
            ->assertJsonFragment(['name' => 'ليلى القلب']);

        $this->getJson('/api/doctors?clinic_id='.$clinic->getKey())
            ->assertOk()
            ->assertJsonFragment(['name' => 'ليلى القلب']);
    }
}
