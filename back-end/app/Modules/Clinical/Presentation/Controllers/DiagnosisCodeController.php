<?php

declare(strict_types=1);

namespace App\Modules\Clinical\Presentation\Controllers;

use App\Core\Authorization\Permissions;
use App\Core\Http\Controllers\ApiController;
use App\Core\Http\DeactivateRecord;
use App\Core\Http\Responses\ApiResponse;
use App\Modules\Clinical\Infrastructure\Persistence\Models\DiagnosisCodeModel;
use App\Modules\Clinical\Presentation\Requests\StoreDiagnosisCodeRequest;
use App\Modules\Clinical\Presentation\Requests\UpdateDiagnosisCodeRequest;
use App\Modules\Clinical\Presentation\Resources\DiagnosisCodeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The diagnosis catalogue. A clinic filter returns only the codes linked to
 * that clinic, which is what the doctor picks from on the patient file.
 */
final class DiagnosisCodeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $codes = DiagnosisCodeModel::query()
            ->with('clinics')
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->search($request->string('search')->toString())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('clinic_id'), fn ($query) => $query->forClinic($request->integer('clinic_id')))
            ->when($request->filled('doctor_id'), fn ($query) => $query->forDoctor($request->integer('doctor_id')))
            ->orderBy('title')
            ->paginate($this->perPage(50));

        return ApiResponse::paginated(DiagnosisCodeResource::collection($codes), $codes);
    }

    public function store(StoreDiagnosisCodeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $code = DiagnosisCodeModel::query()->create([
            'system' => 'local',
            'code' => $this->nextLocalCode(),
            'title' => $data['title'],
            'is_active' => true,
        ]);
        $code->clinics()->sync([(int) $data['clinic_id']]);

        return ApiResponse::created(new DiagnosisCodeResource($code->load('clinics')));
    }

    public function update(UpdateDiagnosisCodeRequest $request, DiagnosisCodeModel $diagnosisCode): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['title'])) {
            $diagnosisCode->update(['title' => $data['title']]);
        }

        if (isset($data['clinic_id'])) {
            $diagnosisCode->clinics()->sync([(int) $data['clinic_id']]);
        }

        return ApiResponse::resource(new DiagnosisCodeResource($diagnosisCode->load('clinics')));
    }

    private function nextLocalCode(): string
    {
        $latest = DiagnosisCodeModel::query()
            ->where('system', 'local')
            ->orderByDesc('id')
            ->value('code');
        $number = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $number = ((int) $matches[1]) + 1;
        }

        return 'DX-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    public function destroy(Request $request, DiagnosisCodeModel $diagnosisCode): JsonResponse
    {
        DeactivateRecord::run($request, $diagnosisCode, Permissions::DEPARTMENTS_MANAGE);

        return ApiResponse::noContent();
    }
}
