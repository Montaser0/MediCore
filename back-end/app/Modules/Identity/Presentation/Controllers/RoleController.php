<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Identity\Presentation\Requests\SyncRolePermissionsRequest;
use App\Modules\Identity\Presentation\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RoleController extends ApiController
{
    public function index(): JsonResponse
    {
        $roles = Role::query()->with('permissions')->withCount('users')->orderBy('name')->get();

        return ApiResponse::data(RoleResource::collection($roles)->resolve());
    }

    public function permissions(): JsonResponse
    {
        // Grouped by module prefix so the admin UI can render one section per
        // area instead of a flat list of ~70 checkboxes.
        $grouped = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->groupBy(fn (string $name) => explode('.', $name)[0])
            ->map(fn ($names) => $names->values()->all())
            ->all();

        return ApiResponse::data($grouped);
    }

    public function store(SyncRolePermissionsRequest $request): JsonResponse
    {
        $role = Role::findOrCreate((string) $request->input('name'));
        $role->syncPermissions($request->input('permissions', []));

        return ApiResponse::created(new RoleResource($role->load('permissions')));
    }

    public function update(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        if ($request->filled('name')) {
            $role->update(['name' => (string) $request->input('name')]);
        }

        $role->syncPermissions($request->input('permissions', []));

        return ApiResponse::resource(new RoleResource($role->load('permissions')));
    }
}
