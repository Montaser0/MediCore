<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'ultrasound' => 'echo',
            'mammography' => 'xray',
            'fluoroscopy' => 'xray',
            'nuclear_medicine' => 'xray',
        ];

        foreach ($map as $from => $to) {
            DB::table('radiology_services')->where('modality', $from)->update(['modality' => $to]);
        }

        $now = now();
        $panorama = [
            ['بانوراما أسنان', 'PAN-DENT', 25000],
            ['بانوراما الفكين', 'PAN-JAW', 30000],
            ['بانوراما الجيوب', 'PAN-SINUS', 28000],
        ];

        foreach ($panorama as [$name, $code, $price]) {
            $exists = DB::table('radiology_services')->where('code', $code)->exists();
            if ($exists) {
                DB::table('radiology_services')->where('code', $code)->update(['modality' => 'panorama']);

                continue;
            }

            DB::table('radiology_services')->insert([
                'name' => $name,
                'code' => $code,
                'modality' => 'panorama',
                'price' => $price,
                'requires_contrast' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $role = Role::query()->where('name', 'radiologist')->where('guard_name', 'web')->first();
        if ($role !== null) {
            $role->syncPermissions(Permissions::roles()['radiologist']);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        DB::table('radiology_services')->where('modality', 'echo')->update(['modality' => 'ultrasound']);
        DB::table('radiology_services')->whereIn('code', ['PAN-DENT', 'PAN-JAW', 'PAN-SINUS'])->delete();

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $role = Role::query()->where('name', 'radiologist')->where('guard_name', 'web')->first();
        if ($role !== null) {
            $role->revokePermissionTo(Permissions::RADIOLOGY_SERVICES_MANAGE);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
