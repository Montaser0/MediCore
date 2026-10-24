<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Infrastructure\Persistence\Models;

use App\Core\Authorization\Permissions;
use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class BloodProductModel extends BaseModel
{
    protected $table = 'blood_products';

    private static bool $catalogReady = false;

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Production servers can be stuck with no catalogue table, or with the
     * retired product_type column still required. Repair that before reading
     * or inserting so definition does not depend on a manual migrate.
     */
    public static function ensureCatalog(): void
    {
        if (self::$catalogReady) {
            return;
        }

        if (! Schema::hasTable('blood_products')) {
            try {
                Schema::create('blood_products', function (Blueprint $table): void {
                    $table->charset = 'utf8mb4';
                    $table->collation = 'utf8mb4_unicode_ci';
                    $table->id();
                    $table->string('name');
                    $table->string('code', 50)->unique();
                    $table->decimal('price', 12, 2)->default(0);
                    $table->boolean('is_active')->default(true);
                    $table->timestamps();
                });
            } catch (QueryException $exception) {
                if (! Schema::hasTable('blood_products')) {
                    throw $exception;
                }
            }
        }

        if (Schema::hasColumn('blood_products', 'product_type')) {
            try {
                Schema::table('blood_products', function (Blueprint $table): void {
                    $table->dropIndex(['product_type', 'is_active']);
                });
            } catch (Throwable) {
                // The composite index is already gone on some servers.
            }

            try {
                Schema::table('blood_products', function (Blueprint $table): void {
                    $table->dropColumn('product_type');
                });
            } catch (Throwable) {
                // storeCatalog still fills the legacy column when it cannot be dropped.
            }
        }

        if (self::needsUtf8mb4()) {
            DB::statement('ALTER TABLE blood_products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }

        if (Schema::hasTable('blood_products') && DB::table('blood_products')->count() === 0) {
            $now = now();
            $rows = [
                ['name' => 'كريات مكثفة', 'code' => 'packed_rbc', 'price' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'بلازما', 'code' => 'plasma', 'price' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'صفيحات', 'code' => 'platelets', 'price' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ];

            try {
                DB::table('blood_products')->insert($rows);
            } catch (QueryException $exception) {
                if (! str_contains($exception->getMessage(), 'product_type')) {
                    throw $exception;
                }

                foreach ($rows as &$row) {
                    $row['product_type'] = $row['code'];
                }
                unset($row);

                DB::table('blood_products')->insert($rows);
            }
        }

        self::ensureManagePermission();
        self::$catalogReady = true;
    }

    /**
     * A missing permission row makes every save a generic 500, including for admin.
     */
    public static function ensureManagePermission(): void
    {
        try {
            Permission::findByName(Permissions::LABORATORY_TESTS_MANAGE, 'web');

            return;
        } catch (PermissionDoesNotExist) {
            Permission::findOrCreate(Permissions::LABORATORY_TESTS_MANAGE, 'web');
        }

        foreach (['admin', 'lab_supervisor'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role instanceof Role) {
                $role->givePermissionTo(Permissions::LABORATORY_TESTS_MANAGE);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function storeCatalog(array $attributes): self
    {
        self::ensureCatalog();

        if (! array_key_exists('is_active', $attributes)) {
            $attributes['is_active'] = true;
        }

        try {
            return static::query()->create($attributes);
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'product_type')) {
                throw $exception;
            }

            $attributes['product_type'] = mb_substr((string) ($attributes['code'] ?? 'other'), 0, 30);
            $model = new static;
            $model->forceFill($attributes)->save();

            return $model;
        }
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', '%'.$term.'%')
                ->orWhere('code', 'like', $term.'%');
        });
    }

    private static function needsUtf8mb4(): bool
    {
        if (! Schema::hasTable('blood_products') || Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }

        $row = DB::selectOne(
            'select TABLE_COLLATION as collation from information_schema.TABLES where TABLE_SCHEMA = database() and TABLE_NAME = ?',
            ['blood_products'],
        );

        $collation = is_object($row) ? (string) ($row->collation ?? '') : '';

        return $collation !== '' && ! str_starts_with($collation, 'utf8mb4');
    }
}
