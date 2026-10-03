<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use App\Modules\Departments\Infrastructure\Persistence\Models\DepartmentModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\StaffModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
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
 */
final class UserModel extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'staff_id',
        'username',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
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
     * Permission names flattened for the frontend guard components.
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        return $this->getAllPermissions()->pluck('name')->all();
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
