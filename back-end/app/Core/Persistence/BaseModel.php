<?php

declare(strict_types=1);

namespace App\Core\Persistence;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared base for every Eloquent model in the system. Models live in the
 * Infrastructure layer of their module and are suffixed with `Model` to keep
 * them clearly separated from domain entities of the same name.
 */
abstract class BaseModel extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
