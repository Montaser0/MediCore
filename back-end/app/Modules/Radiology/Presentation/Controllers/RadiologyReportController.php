<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Radiology\Application\Actions\CompleteRadiologyReportAction;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyReportModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyStudyModel;
use App\Modules\Radiology\Presentation\Requests\StoreRadiologyReportRequest;
use App\Modules\Radiology\Presentation\Resources\RadiologyReportResource;
use Illuminate\Http\JsonResponse;

final class RadiologyReportController extends ApiController
{
    public function store(
        StoreRadiologyReportRequest $request,
        RadiologyStudyModel $study,
        CompleteRadiologyReportAction $action,
    ): JsonResponse {
        return ApiResponse::created(new RadiologyReportResource(
            $action->execute($study, $request->validated())
        ));
    }

    public function show(RadiologyReportModel $radiologyReport): JsonResponse
    {
        return ApiResponse::resource(new RadiologyReportResource(
            $radiologyReport->load(['reportedBy', 'verifiedBy', 'study'])
        ));
    }
}
