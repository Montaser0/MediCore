<?php

declare(strict_types=1);

namespace App\Core\Http\Controllers;

use App\Core\Http\Responses\ApiResponse;
use App\Core\Modules\ModuleRegistry;
use Illuminate\Http\JsonResponse;

final class SystemModulesController extends ApiController
{
    public function show(ModuleRegistry $modules): JsonResponse
    {
        return ApiResponse::data($modules->payload());
    }
}
