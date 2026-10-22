<?php

declare(strict_types=1);

namespace App\Modules\Documents\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Documents\Application\Actions\DeleteDocumentAction;
use App\Modules\Documents\Application\Actions\DownloadDocumentAction;
use App\Modules\Documents\Application\Actions\DownloadImagingStudyAction;
use App\Modules\Documents\Application\Actions\UploadDocumentAction;
use App\Modules\Documents\Application\Queries\ResolveDocumentExamNames;
use App\Modules\Documents\Infrastructure\Persistence\Models\FileLinkModel;
use App\Modules\Documents\Infrastructure\Persistence\Models\FileModel;
use App\Modules\Documents\Presentation\Requests\UploadDocumentRequest;
use App\Modules\Documents\Presentation\Resources\FileResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentController extends ApiController
{
    public function store(UploadDocumentRequest $request, UploadDocumentAction $action): JsonResponse
    {
        $reference = $action->attach(
            file: $request->file('file'),
            entityType: (string) $request->input('entity_type'),
            entityId: $request->integer('entity_id'),
            category: $request->input('category'),
            patientId: $request->integer('patient_id') ?: null,
            encounterId: $request->integer('encounter_id') ?: null,
        );

        return ApiResponse::data($reference->toArray(), status: 201);
    }

    /**
     * Authorised streaming/download endpoint. This is the only way file bytes
     * leave the server.
     */
    public function download(Request $request, FileModel $file, DownloadDocumentAction $action): StreamedResponse
    {
        abort_unless($request->user()?->can(Permissions::DOCUMENTS_VIEW) === true, 403);

        return $action->execute($file, $request->boolean('inline'));
    }

    public function show(FileModel $file): JsonResponse
    {
        return ApiResponse::resource(new FileResource($file->load(['uploader', 'links'])));
    }

    /**
     * Every document filed against a patient, optionally narrowed to one
     * encounter or one category. Powers the Documents tab of the patient file.
     */
    public function forPatient(Request $request, int $patientId, ResolveDocumentExamNames $examNames): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::DOCUMENTS_VIEW) === true, 403);

        $files = FileModel::query()
            ->with('uploader')
            ->whereHas('links', fn ($query) => $query
                ->where('patient_id', $patientId)
                ->when($request->filled('encounter_id'), fn ($inner) => $inner->where('encounter_id', $request->integer('encounter_id')))
                ->when($request->filled('category'), fn ($inner) => $inner->where('category', (string) $request->string('category'))))
            ->orderByDesc('created_at')
            ->paginate($this->perPage());

        $this->attachExamNames($files, $examNames);

        return ApiResponse::paginated(FileResource::collection($files), $files);
    }

    /**
     * The whole imaging study as one ZIP, for the patient file and the
     * radiology desk. Bytes still leave only through an authorised download.
     */
    public function imagingArchive(Request $request, int $patientId, int $study, DownloadImagingStudyAction $action): BinaryFileResponse
    {
        abort_unless($request->user()?->can(Permissions::DOCUMENTS_VIEW) === true, 403);

        return $action->execute($patientId, $study);
    }

    public function forEntity(Request $request, string $entityType, int $entityId, ResolveDocumentExamNames $examNames): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::DOCUMENTS_VIEW) === true, 403);

        $fileIds = FileLinkModel::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->when($request->filled('category'), fn ($query) => $query->where('category', (string) $request->string('category')))
            ->pluck('file_id');

        $files = FileModel::query()->with('uploader')->whereIn('id', $fileIds)->orderByDesc('created_at')->get();
        $this->attachExamNames($files, $examNames);

        return ApiResponse::data(FileResource::collection($files)->resolve());
    }

    /**
     * @param  LengthAwarePaginator<int, FileModel>|EloquentCollection<int, FileModel>  $files
     */
    private function attachExamNames(LengthAwarePaginator|EloquentCollection $files, ResolveDocumentExamNames $examNames): void
    {
        $collection = $files instanceof LengthAwarePaginator ? $files->getCollection() : $files;
        $collection->load('links');
        $labels = $examNames->map($collection->flatMap(fn (FileModel $file) => $file->links));

        foreach ($collection as $file) {
            $link = $file->links->first();
            $file->setAttribute('exam_name', $link instanceof FileLinkModel ? $examNames->labelFor($link, $labels) : null);
        }
    }

    public function destroy(Request $request, FileModel $file, DeleteDocumentAction $action): JsonResponse
    {
        abort_unless($request->user()?->can(Permissions::DOCUMENTS_DELETE) === true, 403);

        $action->execute($file, $request->boolean('purge'));

        return ApiResponse::noContent();
    }
}
