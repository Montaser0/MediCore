<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Excel\Xlsx;
use App\Modules\Assets\Application\Support\AssetBarcode;
use App\Modules\Assets\Infrastructure\Persistence\Models\ItAssetModel;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabTestModel;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ExcelExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_test_sheet_creates_then_updates(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class, AdminUserSeeder::class]);
        Sanctum::actingAs(UserModel::query()->where('username', 'admin')->firstOrFail());

        $created = $this->import([
            ['الاسم', 'الرمز', 'الفئة', 'السعر'],
            ['سكر صائم', 'GLU-X', 'كيمياء', '1500'],
        ]);

        $created->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('lab_tests', [
            'name' => 'سكر صائم',
            'code' => 'GLU-X',
            'category' => 'chemistry',
            'price' => 1500,
        ]);

        LabTestModel::query()->create([
            'name' => 'يبقى',
            'code' => 'KEEP',
            'category' => 'chemistry',
            'price' => 10,
            'is_active' => true,
        ]);

        $updated = $this->import([
            ['الاسم', 'الرمز', 'الفئة', 'السعر'],
            ['سكر صائم', 'GLU-X', 'كيمياء', '2000'],
        ]);

        $updated->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.created', 0);

        $this->assertSame(2000.0, (float) LabTestModel::query()->where('code', 'GLU-X')->value('price'));
        $this->assertSame(1, LabTestModel::query()->where('code', 'GLU-X')->count());
        $this->assertDatabaseHas('lab_tests', ['code' => 'KEEP', 'name' => 'يبقى']);
    }

    public function test_department_sheet_keeps_rows_missing_from_the_file(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class, AdminUserSeeder::class]);
        Sanctum::actingAs(UserModel::query()->where('username', 'admin')->firstOrFail());
        $hospitalId = (int) HospitalModel::query()->value('id');

        DepartmentModel::query()->create([
            'hospital_id' => $hospitalId,
            'name' => 'قسم يبقى',
            'code' => 'KEEP-D',
            'type' => 'medical',
            'is_active' => true,
        ]);

        $created = $this->importBook('departments', [
            ['الاسم', 'الرمز', 'النوع'],
            ['أشعة', 'RAD-X', 'أشعة'],
        ]);

        $created->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('departments', ['code' => 'KEEP-D', 'name' => 'قسم يبقى']);

        $updated = $this->importBook('departments', [
            ['الاسم', 'الرمز', 'النوع'],
            ['قسم الأشعة', 'RAD-X', 'أشعة'],
        ]);

        $updated->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.created', 0);

        $this->assertSame(1, DepartmentModel::query()->where('code', 'RAD-X')->count());
        $this->assertDatabaseHas('departments', ['code' => 'KEEP-D']);
    }

    public function test_asset_sheet_creates_then_updates_without_replacing_the_barcode(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class, AdminUserSeeder::class]);
        Sanctum::actingAs(UserModel::query()->where('username', 'admin')->firstOrFail());

        $this->importBook('assets', [
            ['النوع', 'تاريخ التسليم'],
            ['لابتوب', ''],
        ])->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.failed', 1);

        $created = $this->importBook('assets', [
            ['النوع', 'الشركة', 'الموديل', 'العهدة لدى', 'تاريخ التسليم'],
            ['لابتوب', 'Dell', 'Latitude', 'أحمد علي', '2026-10-21'],
        ]);

        $created->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.failed', 0);

        $asset = ItAssetModel::query()->firstOrFail();
        $barcode = (string) $asset->barcode;
        $this->assertTrue(AssetBarcode::valid($barcode));
        $this->assertSame('assigned', $asset->status->value);
        $this->assertSame('أحمد علي', $asset->received_by);

        $updated = $this->importBook('assets', [
            ['رقم الأصل', 'النوع', 'الشركة', 'العهدة لدى', 'تاريخ التسليم'],
            [$asset->asset_code, 'طابعة', 'HP', '', '2026-10-22'],
        ]);

        $updated->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.created', 0);

        $asset->refresh();
        $this->assertSame('printer', $asset->category->value);
        $this->assertSame('HP', $asset->brand);
        $this->assertNull($asset->received_by);
        $this->assertSame('in_stock', $asset->status->value);
        $this->assertSame('2026-10-22', $asset->delivered_on?->toDateString());
        $this->assertSame($barcode, $asset->barcode);
    }

    public function test_import_rejects_a_sheet_missing_required_columns(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class, AdminUserSeeder::class]);
        Sanctum::actingAs(UserModel::query()->where('username', 'admin')->firstOrFail());

        $this->import([
            ['الاسم'],
            ['سكر'],
        ])->assertStatus(422);
    }

    public function test_export_requires_authentication(): void
    {
        $this->getJson('/api/excel/lab-tests/export')->assertUnauthorized();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function import(array $rows): \Illuminate\Testing\TestResponse
    {
        return $this->importBook('lab-tests', $rows);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function importBook(string $book, array $rows): \Illuminate\Testing\TestResponse
    {
        $path = Xlsx::write($rows, $book);
        $file = new UploadedFile(
            $path,
            $book.'.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        return $this->post('/api/excel/'.$book.'/import', ['file' => $file]);
    }
}
