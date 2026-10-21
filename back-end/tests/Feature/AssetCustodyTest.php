<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Assets\Application\Support\AssetBarcode;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AssetCustodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_device_is_registered_assigned_returned_and_retired(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class, AdminUserSeeder::class]);
        $user = UserModel::query()->where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($user);
        $hospitalId = (int) ($user->staff?->hospital_id ?: HospitalModel::query()->value('id'));

        $department = DepartmentModel::query()->create([
            'hospital_id' => $hospitalId,
            'name' => 'القسم التقني',
            'code' => 'IT',
            'type' => 'support',
        ]);
        $created = $this->postJson('/api/assets', [
            'hospital_id' => $hospitalId,
            'category' => 'laptop',
            'brand' => 'Dell',
            'model_name' => 'Latitude',
            'received_by' => 'أحمد علي',
            'delivered_on' => '2026-10-21',
            'department_id' => $department->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.category', 'laptop')
            ->assertJsonPath('data.asset_code', 'IT-00001')
            ->assertJsonPath('data.received_by', 'أحمد علي')
            ->assertJsonPath('data.delivered_on', '2026-10-21');

        $barcode = (string) $created->json('data.barcode');
        $this->assertTrue(AssetBarcode::valid($barcode));
        $this->assertStringStartsWith('22', $barcode);

        $id = $created->json('data.id');

        $this->postJson('/api/assets', [
            'hospital_id' => $hospitalId,
            'category' => 'laptop',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['delivered_on']);

        $second = $this->postJson('/api/assets', [
            'hospital_id' => $hospitalId,
            'category' => 'laptop',
            'delivered_on' => '2026-10-21',
        ])->assertCreated()
            ->assertJsonPath('data.received_by', null)
            ->assertJsonPath('data.delivered_on', '2026-10-21');

        $this->assertNotSame($barcode, $second->json('data.barcode'));

        $this->getJson('/api/assets?category=laptop&search=أحمد')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.brand', 'Dell');

        $this->getJson('/api/assets/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.in_stock', 1)
            ->assertJsonPath('data.assigned', 1);

        $this->postJson("/api/assets/{$id}/assign", [
            'received_by' => 'سامر فني',
            'note' => 'مكتب الاستقبال',
        ])->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.received_by', 'سامر فني')
            ->assertJsonPath('data.department.name', 'القسم التقني');

        $this->postJson("/api/assets/{$id}/return", [
            'note' => 'انتهت العهدة',
        ])->assertOk()
            ->assertJsonPath('data.status', 'in_stock')
            ->assertJsonPath('data.received_by', null);

        $this->postJson("/api/assets/{$id}/maintenance", ['note' => 'الشاشة لا تعمل'])
            ->assertOk()
            ->assertJsonPath('data.status', 'maintenance');

        $this->postJson("/api/assets/{$id}/assign", ['received_by' => 'سامر فني'])
            ->assertUnprocessable();

        $this->postJson("/api/assets/{$id}/repair", ['note' => 'تم الاستبدال'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_stock');

        $this->postJson("/api/assets/{$id}/retire", ['note' => 'تالف'])
            ->assertOk()
            ->assertJsonPath('data.status', 'retired');

        $this->getJson("/api/assets/{$id}/movements")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'retired');
    }
}
