<?php

use App\Core\Authorization\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $role = Role::query()->where('name', 'reception')->where('guard_name', 'web')->first();

        if ($role === null) {
            return;
        }

        $role->syncPermissions(Permissions::roles()['reception']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $role = Role::query()->where('name', 'reception')->where('guard_name', 'web')->first();

        if ($role === null) {
            return;
        }

        $role->givePermissionTo([
            Permissions::BILLING_INVOICES_VIEW,
            Permissions::BILLING_INVOICES_CREATE,
            Permissions::BILLING_PAYMENTS_CREATE,
            Permissions::BILLING_CHARGES_VIEW,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
