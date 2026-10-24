<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\BloodProductModel;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class BloodProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_define_a_blood_product(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        $user = UserModel::factory()->withRole('lab_supervisor')->create();
        Sanctum::actingAs($user);

        $this->withoutExceptionHandling();

        $this->getJson('/api/laboratory/blood-products?per_page=200')->assertOk();

        $created = $this->postJson('/api/laboratory/blood-products', [
            'name' => 'afafa',
            'price' => 32,
        ])->assertCreated();

        $created->assertJsonPath('data.is_active', true);
    }

    public function test_definition_still_saves_when_the_legacy_type_column_is_required(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        BloodProductModel::query()->delete();

        Schema::table('blood_products', function ($table): void {
            $table->string('product_type', 30);
        });

        $user = UserModel::factory()->withRole('lab_supervisor')->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/laboratory/blood-products', [
            'name' => 'afafa',
            'price' => 32,
        ])->assertCreated();

        $product = BloodProductModel::query()->where('name', 'afafa')->first();
        $this->assertNotNull($product);
        $this->assertSame('afafa', $product->name);
        $this->assertNotSame('', (string) $product->getAttribute('product_type'));
    }
}

