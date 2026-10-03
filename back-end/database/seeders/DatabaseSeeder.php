<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Order matters: permissions before the admin who is given them, the hospital
 * before anything scoped to it, and reference data last.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            HospitalSeeder::class,
            AdminUserSeeder::class,
            ReferenceDataSeeder::class,
            WardSeeder::class,
            // بيانات وهمية للتطوير: كادر + مرضى + تشغيل لكل الأقسام
            DemoDataSeeder::class,
        ]);
    }
}
