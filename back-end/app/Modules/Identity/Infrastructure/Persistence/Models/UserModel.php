<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use App\Core\Authorization\Permissions;
use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

/**
 * Credentials only. Everything about the human being — name, department, job
 * title — lives on the linked staff record.
 *
 * @property int $id
 * @property int|null $staff_id
 * @property string $username
 * @property string|null $email
 * @property bool $is_active
 * @property array<int, string>|null $ui_screens
 */
final class UserModel extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles {
        checkPermissionTo as spatieCheckPermissionTo;
    }
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'staff_id',
        'username',
        'email',
        'password',
        'is_active',
        'ui_screens',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'ui_screens' => 'array',
    ];

    /**
     * @return BelongsTo<StaffModel, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffModel::class, 'staff_id');
    }

    /**
     * Departments this account may work in, beyond the staff member's home
     * department.
     *
     * @return BelongsToMany<DepartmentModel, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(DepartmentModel::class, 'user_departments', 'user_id', 'department_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function hospitalId(): ?int
    {
        return $this->staff?->hospital_id;
    }

    public function displayName(): string
    {
        return $this->staff?->full_name ?: $this->username;
    }

    /**
     * Name shown on a clinical row. Data-entry accounts keep the role visible
     * so a visit shows which clerk entered it, not only the doctor.
     */
    public function actorLabel(): string
    {
        $name = $this->displayName();

        if ($this->hasRole('data_entry')) {
            return $name.' — مدخل بيانات';
        }

        return $name;
    }

    /**
     * Permission names flattened for the frontend guard components.
     * A saved screen list replaces the role bundle so an unchecked box
     * actually disappears from the menu and from the API.
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        if (is_array($this->ui_screens)) {
            return $this->getDirectPermissions()->pluck('name')->values()->all();
        }

        return $this->getAllPermissions()->pluck('name')->all();
    }

    public function usesCustomScreens(): bool
    {
        return is_array($this->ui_screens);
    }

    /**
     * @param  array<int, string>  $screens
     */
    public function syncUiAccess(string $role, array $screens): void
    {
        $screens = array_values(array_unique(array_values(array_filter(
            $screens,
            static fn (mixed $id): bool => is_string($id) && array_key_exists($id, Permissions::screens()),
        ))));

        $this->forceFill(['ui_screens' => $screens])->save();
        $this->unsetRelation('permissions');
        $this->syncPermissions(Permissions::permissionsFor($role, $screens));
        $this->unsetRelation('permissions');
    }

    /**
     * @param  string|int|Permission|\BackedEnum  $permission
     */
    public function checkPermissionTo($permission, $guardName = null): bool
    {
        if (! is_array($this->ui_screens)) {
            return $this->spatieCheckPermissionTo($permission, $guardName);
        }

        $name = $permission instanceof \BackedEnum ? $permission->value : $permission;

        if ($name instanceof Permission) {
            $name = $name->name;
        }

        if (! is_string($name)) {
            return $this->spatieCheckPermissionTo($permission, $guardName);
        }

        return in_array($name, $this->getDirectPermissions()->pluck('name')->all(), true);
    }

    /**
     * @param  Builder<UserModel>  $query
     * @return Builder<UserModel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
