<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockLevelModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class InventoryItemWarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_item_is_placed_in_the_chosen_warehouse_and_lists_filter_by_it(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class, AdminUserSeeder::class]);
        $user = UserModel::query()->where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($user);
        $hospitalId = (int) ($user->staff?->hospital_id ?: HospitalModel::query()->value('id'));

        $pharmacy = WarehouseModel::query()->create([
            'hospital_id' => $hospitalId,
            'name' => 'صيدلية رئيسية',
            'code' => 'PH-F',
            'is_pharmacy' => true,
            'is_active' => true,
        ]);
        $general = WarehouseModel::query()->create([
            'hospital_id' => $hospitalId,
            'name' => 'مستودع عام',
            'code' => 'GN-F',
            'is_pharmacy' => false,
            'is_active' => true,
        ]);

        $this->postJson('/api/inventory/items', [
            'name' => 'قفازات',
            'sku' => 'GLV-F',
            'unit' => 'علبة',
            'item_type' => 'general',
        ])->assertUnprocessable();

        $this->postJson('/api/inventory/items', [
            'name' => 'قفازات',
            'sku' => 'GLV-F',
            'unit' => 'علبة',
            'item_type' => 'general',
            'warehouse_id' => $general->id,
        ])->assertCreated()
            ->assertJsonPath('data.sku', 'GLV-F');

        $this->postJson('/api/inventory/items', [
            'name' => 'باراسيتامول',
            'sku' => 'PARA-F',
            'unit' => 'علبة',
            'item_type' => 'medicine',
            'warehouse_id' => $general->id,
        ])->assertUnprocessable();

        $this->postJson('/api/inventory/items', [
            'name' => 'باراسيتامول',
            'sku' => 'PARA-F',
            'unit' => 'علبة',
            'item_type' => 'medicine',
            'warehouse_id' => $pharmacy->id,
        ])->assertCreated();

        $this->assertSame(0.0, (float) StockLevelModel::query()->where('warehouse_id', $general->id)->value('quantity'));

        $this->getJson('/api/inventory/items?warehouse_id='.$general->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'GLV-F');

        $this->getJson('/api/inventory/stock-levels?warehouse_id='.$pharmacy->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'PARA-F')
            ->assertJsonPath('data.0.warehouse', 'صيدلية رئيسية');
    }
}
