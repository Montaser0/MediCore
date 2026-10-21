<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Inventory\Application\Support\ItemBarcode;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryItemModel;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ItemBarcodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_items_get_a_kind_prefix_and_a_valid_check_digit(): void
    {
        $general = InventoryItemModel::query()->create([
            'name' => 'شاش',
            'sku' => 'GAUZE-1',
            'unit' => 'علبة',
            'item_type' => 'general',
        ]);
        $medicine = InventoryItemModel::query()->create([
            'name' => 'باراسيتامول',
            'sku' => 'PARA-1',
            'unit' => 'علبة',
            'item_type' => 'medicine',
        ]);

        $this->assertMatchesRegularExpression('/^21\d{11}$/', (string) $general->barcode);
        $this->assertMatchesRegularExpression('/^20\d{11}$/', (string) $medicine->barcode);
        $this->assertTrue(ItemBarcode::valid((string) $general->barcode));
        $this->assertTrue(ItemBarcode::valid((string) $medicine->barcode));
        $this->assertNotSame($general->barcode, $medicine->barcode);
        $this->assertSame('1', ItemBarcode::checkDigit('400638133393'));
    }

    public function test_missing_barcodes_can_be_filled_and_looked_up(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class, AdminUserSeeder::class]);
        Sanctum::actingAs(UserModel::query()->where('username', 'admin')->firstOrFail());

        $item = InventoryItemModel::query()->create([
            'name' => 'قفازات',
            'sku' => 'GLOVE-1',
            'unit' => 'علبة',
            'item_type' => 'general',
        ]);
        InventoryItemModel::query()->whereKey($item->id)->update(['barcode' => null]);

        $this->postJson('/api/inventory/items/generate-barcodes')
            ->assertOk()
            ->assertJsonPath('data.generated', 1);

        $code = (string) InventoryItemModel::query()->whereKey($item->id)->value('barcode');
        $this->assertTrue(ItemBarcode::valid($code));

        $this->getJson('/api/inventory/items/by-barcode/'.$code)
            ->assertOk()
            ->assertJsonPath('data.sku', 'GLOVE-1');

        $this->getJson('/api/inventory/items/by-barcode/0000000000000')
            ->assertNotFound();

        $next = $this->getJson('/api/inventory/items/next-barcode?kind=pharmacy')->assertOk();
        $barcode = (string) $next->json('data.barcode');
        $this->assertTrue(str_starts_with($barcode, '20'));
        $this->assertTrue(ItemBarcode::valid($barcode));
    }
}
