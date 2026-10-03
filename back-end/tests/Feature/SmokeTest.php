<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Database\Seeders\HospitalSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * يتحقق من أن الهيكل كله يقلع: الهجرات، والبذور، والمصادقة، والصلاحيات،
 * ونقاط النهاية الأساسية.
 */
final class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_endpoint_is_public(): void
    {
        $this->getJson('/api/version')->assertOk();
    }

    public function test_protected_endpoints_reject_anonymous_callers(): void
    {
        $this->getJson('/api/patients')->assertUnauthorized();
        $this->getJson('/api/reports/dashboard')->assertUnauthorized();
    }

    public function test_reference_data_seeds_in_arabic(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class, ReferenceDataSeeder::class]);

        $this->assertDatabaseHas('departments', ['code' => 'LAB', 'name' => 'المخبر']);
        $this->assertDatabaseHas('lab_tests', ['code' => 'CBC', 'name' => 'تعداد دم كامل']);
        $this->assertDatabaseHas('services', ['code' => 'CONS-GEN', 'name' => 'معاينة عامة']);
    }

    public function test_patient_factory_produces_arabic_records(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        $patient = PatientModel::factory()->create();

        // الحروف العربية في النطاق U+0600..U+06FF
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $patient->first_name);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $patient->last_name);
        $this->assertMatchesRegularExpression('/^09\d{8}$/', $patient->phone);
    }

    public function test_permissions_are_enforced_per_role(): void
    {
        $this->seed([PermissionSeeder::class, HospitalSeeder::class]);

        $cashier = UserModel::factory()->withRole('cashier')->create();
        Sanctum::actingAs($cashier);

        // المحاسب يرى الفواتير ولا يرى نتائج المخبر.
        $this->getJson('/api/billing/invoices')->assertOk();
        $this->getJson('/api/laboratory/worklist')->assertForbidden();
    }
}
