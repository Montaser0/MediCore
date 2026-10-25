<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockLevelModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Pharmacy\Application\Actions\CatalogueDrugAction;
use App\Modules\Pharmacy\Application\Actions\CreatePharmacySaleAction;
use App\Modules\Pharmacy\Application\Actions\ProvisionMedicationStockAction;
use App\Modules\Pharmacy\Application\Actions\ReceiveMedicationStockAction;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\DrugCatalogueItemModel;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\MedicationModel;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MedicationStockLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_adopting_a_catalogue_drug_does_not_create_stock(): void
    {
        $hospitalId = $this->hospitalId();
        $medication = $this->adopt($hospitalId, 'CAT-1', 'أموكسيسيلين');

        $this->assertNull($medication->inventory_item_id);
        $this->assertSame(0.0, (float) $medication->quantity);
        $this->assertSame(0, InventoryItemModel::query()->count());
        $this->assertSame(0, StockMovementModel::query()->count());
    }

    public function test_provision_links_one_item_and_a_second_call_returns_it(): void
    {
        $hospitalId = $this->hospitalId();
        $medication = $this->adopt($hospitalId, 'CAT-2', 'باراسيتامول');
        $warehouse = $this->pharmacy($hospitalId, 'PH-A');
        $this->act();

        $first = $this->postJson('/api/pharmacy/medications/'.$medication->id.'/provision', [
            'unit' => 'علبة',
            'warehouse_id' => $warehouse->id,
        ])->assertOk()
            ->assertJsonPath('data.stock_ready', true);

        $itemId = (int) $first->json('data.inventory_item_id');

        $this->postJson('/api/pharmacy/medications/'.$medication->id.'/provision', [
            'warehouse_id' => $warehouse->id,
        ])->assertOk()
            ->assertJsonPath('data.inventory_item_id', $itemId);

        $this->assertSame(1, InventoryItemModel::query()->count());
        $this->assertSame(0.0, (float) StockLevelModel::query()->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertSame(0, StockMovementModel::query()->count());
    }

    public function test_receive_posts_one_purchase_and_leaves_the_shelf_column(): void
    {
        $hospitalId = $this->hospitalId();
        $medication = $this->adopt($hospitalId, 'CAT-3', 'أيبوبروفين');
        $medication->forceFill(['quantity' => 5, 'price' => 1])->save();
        $warehouse = $this->pharmacy($hospitalId, 'PH-B');
        $this->act();

        $this->postJson('/api/pharmacy/medications/'.$medication->id.'/receive', [
            'warehouse_id' => $warehouse->id,
            'quantity' => 4,
            'batch_number' => 'B-1',
            'expiry_date' => now()->addYear()->toDateString(),
            'unit_cost' => 2.5,
            'price' => 8,
        ])->assertOk()
            ->assertJsonPath('data.stock_ready', true)
            ->assertJsonPath('data.price', 8);

        $medication->refresh();
        $this->assertNotNull($medication->inventory_item_id);
        $this->assertSame(5.0, (float) $medication->quantity);
        $this->assertSame(8.0, (float) $medication->price);
        $this->assertSame(4.0, (float) StockLevelModel::query()->where('inventory_item_id', $medication->inventory_item_id)->value('quantity'));
        $this->assertSame(1, StockMovementModel::query()->where('type', 'purchase')->where('reference_type', 'medication')->count());
    }

    public function test_receive_refuses_a_general_warehouse_and_another_facility(): void
    {
        $hospitalId = $this->hospitalId();
        $medication = $this->adopt($hospitalId, 'CAT-4', 'سيتريزين');
        $general = WarehouseModel::query()->create([
            'hospital_id' => $hospitalId,
            'name' => 'مستودع عام',
            'code' => 'GN-A',
            'is_pharmacy' => false,
            'is_active' => true,
        ]);
        $other = HospitalModel::query()->create(['name' => 'منشأة أخرى', 'code' => 'OTHER']);
        $foreign = $this->pharmacy((int) $other->id, 'PH-X');
        $this->act();

        $payload = [
            'quantity' => 1,
            'batch_number' => 'B-2',
            'expiry_date' => now()->addMonths(6)->toDateString(),
        ];

        $this->postJson('/api/pharmacy/medications/'.$medication->id.'/receive', [
            ...$payload,
            'warehouse_id' => $general->id,
        ])->assertUnprocessable();

        $this->postJson('/api/pharmacy/medications/'.$medication->id.'/receive', [
            ...$payload,
            'warehouse_id' => $foreign->id,
        ])->assertUnprocessable();

        $this->assertNull($medication->refresh()->inventory_item_id);
        $this->assertSame(0, StockMovementModel::query()->count());
    }

    public function test_two_facilities_get_separate_items_for_the_same_catalogue_row(): void
    {
        $first = $this->hospitalId();
        $second = (int) HospitalModel::query()->create(['name' => 'صيدلية ثانية', 'code' => 'PH2'])->id;
        $catalogue = $this->catalogue('CAT-SHARED', 'ميتفورمين');
        $adopt = app(CatalogueDrugAction::class);
        $provision = app(ProvisionMedicationStockAction::class);

        $left = $provision->execute($adopt->adopt($catalogue, $first), $first);
        $right = $provision->execute($adopt->adopt($catalogue, $second), $second);

        $this->assertNotSame($left->id, $right->id);
        $this->assertSame(0.0, $left->totalQuantity());
        $this->assertSame(0.0, $right->totalQuantity());
    }

    public function test_selling_a_received_medicine_draws_stock_once(): void
    {
        $hospitalId = $this->hospitalId();
        $this->act();
        $medication = $this->adopt($hospitalId, 'CAT-SALE', 'لوراتادين');
        $warehouse = $this->pharmacy($hospitalId, 'PH-S');

        app(ReceiveMedicationStockAction::class)->execute($medication, $hospitalId, [
            'warehouse_id' => $warehouse->id,
            'quantity' => 4,
            'batch_number' => 'B-S',
            'expiry_date' => now()->addYear()->toDateString(),
            'unit_cost' => 1,
        ]);

        app(CreatePharmacySaleAction::class)->execute([
            ['medication_id' => $medication->id, 'quantity' => 1],
        ]);

        $medication->refresh();
        $this->assertSame(0.0, (float) $medication->quantity);
        $this->assertSame(3.0, (float) StockLevelModel::query()->where('inventory_item_id', $medication->inventory_item_id)->value('quantity'));
        $this->assertSame(1, StockMovementModel::query()->where('type', 'sale')->count());
    }

    public function test_a_second_medicine_cannot_claim_the_same_inventory_item(): void
    {
        $hospitalId = $this->hospitalId();
        $first = $this->adopt($hospitalId, 'CAT-5', 'دواء أول');
        $second = $this->adopt($hospitalId, 'CAT-6', 'دواء ثان');
        $item = app(ProvisionMedicationStockAction::class)->execute($first, $hospitalId);
        $this->act();

        $this->patchJson('/api/pharmacy/medications/'.$second->id, [
            'name' => $second->name,
            'inventory_item_id' => $item->id,
        ])->assertUnprocessable();

        $this->expectException(QueryException::class);
        MedicationModel::query()->whereKey($second->id)->update(['inventory_item_id' => $item->id]);
    }

    private function hospitalId(): int
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class, AdminUserSeeder::class]);

        return (int) HospitalModel::query()->where('code', config('hms.default_hospital_code', 'MAIN'))->value('id');
    }

    private function act(): void
    {
        Sanctum::actingAs(UserModel::query()->where('username', 'admin')->firstOrFail());
    }

    private function adopt(int $hospitalId, string $sourceId, string $name): MedicationModel
    {
        return app(CatalogueDrugAction::class)->adopt($this->catalogue($sourceId, $name), $hospitalId);
    }

    private function catalogue(string $sourceId, string $name): DrugCatalogueItemModel
    {
        return DrugCatalogueItemModel::query()->create([
            'source_record_id' => $sourceId,
            'name' => $name,
            'generic_name' => $name,
            'strength' => '500 mg',
            'dosage_form' => 'قرص',
            'product_type' => 'دواء',
        ]);
    }

    private function pharmacy(int $hospitalId, string $code): WarehouseModel
    {
        return WarehouseModel::query()->create([
            'hospital_id' => $hospitalId,
            'name' => 'صيدلية',
            'code' => $code,
            'is_pharmacy' => true,
            'is_active' => true,
        ]);
    }
}
