<?php

declare(strict_types=1);

namespace App\Core\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Catalog rows stay in the database so old orders still resolve.
 * Deactivation hides them from the active lists.
 */
final class DeactivateRecord
{
    public static function run(Request $request, Model $model, string $permission): void
    {
        abort_unless($request->user()?->can($permission) === true, 403);

        $model->forceFill(['is_active' => false])->save();
    }
}
