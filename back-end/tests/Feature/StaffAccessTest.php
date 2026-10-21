<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Authorization\Permissions;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_preset_keeps_the_clinical_file_and_hides_other_desks(): void
    {
        $permissions = Permissions::permissionsFor('doctor', Permissions::screensForRole('doctor'));

        $this->assertContains(Permissions::CLINICAL_NOTES_CREATE, $permissions);
        $this->assertContains(Permissions::PATIENTS_VIEW, $permissions);
        $this->assertContains(Permissions::LABORATORY_ORDERS_VIEW, $permissions);
        $this->assertNotContains(Permissions::LABORATORY_RESULTS_CREATE, $permissions);
        $this->assertNotContains(Permissions::STAFF_MANAGE, $permissions);
        $this->assertSame([], Permissions::permissionsFor('doctor', []));
    }

    public function test_staff_form_grants_and_revokes_screens(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        $admin = UserModel::factory()->withRole('admin')->create();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/staff', [
            'hospital_id' => HospitalModel::query()->value('id'),
            'first_name' => 'أحمد',
            'last_name' => 'الخطيب',
            'department_id' => DepartmentModel::query()->where('code', 'ENT')->value('id'),
            'role' => 'doctor',
            'username' => 'ahmad.doc',
            'password' => 'secret1',
            'screens' => ['patients', 'laboratory'],
        ])->assertCreated();

        $created->assertJsonPath('data.screens', ['patients', 'laboratory']);
        $staffId = $created->json('data.id');

        $doctor = UserModel::query()->where('username', 'ahmad.doc')->firstOrFail();
        $this->assertTrue($doctor->can(Permissions::LABORATORY_RESULTS_CREATE));
        $this->assertTrue($doctor->can(Permissions::CLINICAL_NOTES_CREATE));
        $this->assertFalse($doctor->can(Permissions::STAFF_MANAGE));

        Sanctum::actingAs($doctor);
        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.custom_permissions', true)
            ->assertJsonFragment(['screens' => ['patients', 'laboratory']]);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/staff/{$staffId}", [
            'screens' => ['laboratory'],
        ])->assertOk()
            ->assertJsonPath('data.screens', ['laboratory']);

        $doctor->refresh();
        $this->assertTrue($doctor->can(Permissions::LABORATORY_RESULTS_CREATE));
        $this->assertFalse($doctor->can(Permissions::CLINICAL_NOTES_CREATE));
        $this->assertFalse($doctor->can(Permissions::ENCOUNTERS_DISCHARGE));
    }
}
