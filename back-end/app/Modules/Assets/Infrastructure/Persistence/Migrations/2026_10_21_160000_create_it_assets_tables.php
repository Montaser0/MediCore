<?php

declare(strict_types=1);

use App\Core\Authorization\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->string('asset_code', 50);
            $table->string('barcode', 100)->nullable();
            $table->string('category', 40);
            $table->string('brand', 100)->nullable();
            $table->string('model_name', 100)->nullable();
            $table->string('status', 32);
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('location', 150)->nullable();
            $table->string('received_by', 150)->nullable();
            $table->date('delivered_on')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'asset_code'], 'uq_it_asset_code');
            $table->unique(['hospital_id', 'barcode'], 'uq_it_asset_barcode');
            $table->index(['hospital_id', 'status'], 'idx_it_assets_status');
            $table->index(['hospital_id', 'category'], 'idx_it_assets_category');
        });

        Schema::create('it_asset_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('it_asset_id')->constrained('it_assets')->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained('hospitals');
            $table->string('type', 32);
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('from_holder', 150)->nullable();
            $table->string('to_holder', 150)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['it_asset_id', 'occurred_at'], 'idx_it_asset_movements');
        });

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        foreach ([Permissions::ASSETS_VIEW, Permissions::ASSETS_MANAGE] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::query()->where('name', 'admin')->where('guard_name', 'web')->first()
            ?->givePermissionTo([Permissions::ASSETS_VIEW, Permissions::ASSETS_MANAGE]);

        Role::findOrCreate('technician', 'web')->syncPermissions([
            Permissions::ASSETS_VIEW,
            Permissions::ASSETS_MANAGE,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('it_asset_movements');
        Schema::dropIfExists('it_assets');
    }
};
