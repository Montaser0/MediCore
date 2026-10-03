<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Presentation\Controllers;

use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Core\Storage\FileReference;
use App\Core\Support\Contracts\DocumentService;
use App\Modules\Radiology\Application\Actions\StartStudyAction;
use App\Modules\Radiology\Application\Actions\UploadRadiologyImageAction;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyStudyModel;
use App\Modules\Radiology\Presentation\Requests\StartStudyRequest;
use App\Modules\Radiology\Presentation\Requests\UploadStudyImagesRequest;
use App\Modules\Radiology\Presentation\Resources\RadiologyStudyResource;
use Illuminate\Http\JsonResponse;

final class RadiologyStudyController extends ApiController
{
    public function store(StartStudyRequest $request, RadiologyOrderItemModel $radiologyItem, StartStudyAction $action): JsonResponse
    {
        return ApiResponse::created(new RadiologyStudyResource(
            $action->execute($radiologyItem, $request->validated())->load('performedBy')
        ));
    }

    public function show(RadiologyStudyModel $study): JsonResponse
    {
        return ApiResponse::resource(new RadiologyStudyResource(
            $study->load(['performedBy', 'report.reportedBy', 'report.verifiedBy'])
        ));
    }

    public function uploadImages(
        UploadStudyImagesRequest $request,
        RadiologyStudyModel $study,
        UploadRadiologyImageAction $action,
    ): JsonResponse {
        $references = $action->execute($study, $request->file('images', []));

        return ApiResponse::data([
            'study' => (new RadiologyStudyResource($study->fresh()))->resolve(),
            'files' => array_map(static fn (FileReference $file) => $file->toArray(), $references),
        ]);
    }

    /**
     * The image list for the viewer. Only metadata and authorised download
     * paths are returned; the bytes come from the Documents endpoint.
     */
    public function images(RadiologyStudyModel $study, DocumentService $documents): JsonResponse
    {
        $files = $documents->listFor('radiology_study', $study->getKey(), 'radiology_image');

        return ApiResponse::data(array_map(static fn (FileReference $file) => $file->toArray(), $files));
    }
}
