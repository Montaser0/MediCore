<?php

declare(strict_types=1);

namespace App\Modules\Settings\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Settings\Application\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SettingController extends ApiController
{
    public function index(Request $request, SettingsRepository $settings, CurrentUser $currentUser): JsonResponse
    {
        $group = (string) $request->string('group', 'general');

        return ApiResponse::data([
            'group' => $group,
            'values' => $settings->group($group, $currentUser->hospitalId()),
        ]);
    }

    public function update(Request $request, SettingsRepository $settings, CurrentUser $currentUser): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::SETTINGS_MANAGE) === true, 403);

        $validated = $request->validate([
            'group' => ['required', 'string', 'max:100'],
            'values' => ['required', 'array'],
            'scope' => ['sometimes', 'in:hospital,global'],
        ]);

        $hospitalId = ($validated['scope'] ?? 'hospital') === 'global'
            ? null
            : $currentUser->hospitalId();

        foreach ($validated['values'] as $key => $value) {
            $settings->set($validated['group'], (string) $key, $value, $hospitalId, get_debug_type($value));
        }

        return ApiResponse::data([
            'group' => $validated['group'],
            'values' => $settings->group($validated['group'], $hospitalId),
        ]);
    }

    public function destroy(Request $request, SettingsRepository $settings, CurrentUser $currentUser): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::SETTINGS_MANAGE) === true, 403);

        $validated = $request->validate([
            'group' => ['required', 'string', 'max:100'],
            'key' => ['required', 'string', 'max:100'],
        ]);

        $settings->forget($validated['group'], $validated['key'], $currentUser->hospitalId());

        return ApiResponse::noContent();
    }
}
