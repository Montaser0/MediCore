<?php

declare(strict_types=1);

namespace App\Modules\Hospital\Presentation\Controllers;

use App\Core\Auth\CurrentUser;
use App\Core\Authorization\Permissions;
use App\Core\Exceptions\ResourceNotFoundException;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Hospital\Presentation\Resources\HospitalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HospitalController extends ApiController
{
    public function index(): JsonResponse
    {
        $hospitals = HospitalModel::query()->orderBy('name')->get();

        return ApiResponse::data(HospitalResource::collection($hospitals)->resolve());
    }

    /**
     * The hospital the acting user belongs to, used by the frontend header and
     * printed documents.
     */
    public function current(CurrentUser $currentUser): JsonResponse
    {
        $hospital = $currentUser->hospitalId() !== null
            ? HospitalModel::query()->find($currentUser->hospitalId())
            : HospitalModel::query()->where('code', config('hms.default_hospital_code'))->first();

        if ($hospital === null) {
            throw ResourceNotFoundException::for('Hospital', (string) config('hms.default_hospital_code'));
        }

        return ApiResponse::resource(new HospitalResource($hospital));
    }

    public function update(Request $request, HospitalModel $hospital): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::SETTINGS_MANAGE) === true, 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'logo_path' => ['nullable', 'string', 'max:500'],
            'timezone' => ['sometimes', 'string', 'max:100'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $hospital->update($validated);

        return ApiResponse::resource(new HospitalResource($hospital));
    }
}
