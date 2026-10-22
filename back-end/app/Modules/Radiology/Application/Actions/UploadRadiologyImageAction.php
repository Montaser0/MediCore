<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Support\Contracts\DocumentService;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Modules\Radiology\Domain\Enums\RadiologyItemStatus;
use App\Modules\Radiology\Domain\Enums\StudyStatus;
use App\Modules\Radiology\Domain\Events\ImagesUploaded;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyStudyModel;
use App\Modules\Radiology\Infrastructure\Services\AcceptedImage;
use App\Modules\Radiology\Infrastructure\Services\DicomService;
use App\Modules\Radiology\Infrastructure\Services\ImagingIntake;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Images are stored through the shared Documents module: bytes on disk,
 * metadata in the database, and a link to both the study and the patient file.
 * A ZIP or a folder is expanded first. DICOM is kept as DICOM.
 */
final class UploadRadiologyImageAction
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly ImagingIntake $intake,
        private readonly DicomService $dicom,
        private readonly ChangeRadiologyItemStatusAction $changeStatus,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function execute(RadiologyStudyModel $study, array $files): ImagingUploadResult
    {
        if ($files === []) {
            throw BusinessRuleException::make('اختر صورة واحدة على الأقل للرفع.');
        }

        $package = $this->intake->collect($files);

        try {
            $study->loadMissing(['radiologyOrder.order.encounter.patient', 'orderItem.service']);
            $order = $study->radiologyOrder?->order;
            $patient = $order?->encounter?->patient;
            $patientId = $patient?->getKey();
            $existing = $this->documents->countFor('radiology_study', $study->getKey(), 'radiology_image');
            $ceiling = $this->ceiling();

            if ($existing + count($package->files) > $ceiling) {
                throw BusinessRuleException::make('عدد ملفات هذا الفحص يتجاوز الحد المسموح ('.$ceiling.' ملفاً).');
            }

            $warnings = $this->warnings($package->files, $patient?->full_name, $package->skipped);
            $announce = (int) $study->image_count < 1;
            $uid = $this->firstUid($package->files);
            $accession = $this->firstAccession($package->files);

            $references = DB::transaction(function () use ($study, $package, $order, $patientId, $announce, $uid, $accession): array {
                $stored = [];

                foreach ($package->files as $image) {
                    $stored[] = $this->documents->attach(
                        file: $image->file,
                        entityType: 'radiology_study',
                        entityId: $study->getKey(),
                        category: 'radiology_image',
                        patientId: $patientId !== null ? (int) $patientId : null,
                        encounterId: $order?->encounter_id,
                    );
                }

                $attributes = [
                    'image_count' => $this->documents->countFor('radiology_study', $study->getKey(), 'radiology_image'),
                    'status' => StudyStatus::Completed->value,
                ];

                if ($uid !== null && ($study->study_instance_uid === null || $study->study_instance_uid === '')) {
                    $attributes['study_instance_uid'] = $uid;
                }

                if ($accession !== null && ($study->accession_number === null || $study->accession_number === '')) {
                    $attributes['accession_number'] = $accession;
                }

                $study->forceFill($attributes)->save();

                $item = $study->orderItem;

                if ($item !== null && $item->status->canTransitionTo(RadiologyItemStatus::Completed)) {
                    $this->changeStatus->execute($item, RadiologyItemStatus::Completed);
                }

                ImagesUploaded::dispatch($study, count($stored));

                if ($announce) {
                    $this->notifications->toPermissionHolders(
                        permission: 'radiology.reports.create',
                        type: 'radiology.study_ready',
                        title: 'الصور جاهزة لكتابة التقرير',
                        message: ($item?->service?->name ?? 'Study').' '.$study->study_number.' has images available.',
                        entityType: 'radiology_study',
                        entityId: $study->getKey(),
                        payload: ['image_count' => $study->image_count],
                    );
                }

                return $stored;
            });

            return new ImagingUploadResult($references, $warnings, $package->skipped);
        } finally {
            $package->cleanup();
        }
    }

    /**
     * @param  array<int, AcceptedImage>  $images
     * @return array<int, string>
     */
    private function warnings(array $images, ?string $patientName, int $skipped): array
    {
        $warnings = [];
        $seen = [];

        foreach ($images as $image) {
            $dicomName = $image->dicom?->patientName;

            if ($dicomName === null || isset($seen[$dicomName])) {
                continue;
            }

            $seen[$dicomName] = true;

            if ($this->dicom->namesConflict($dicomName, $patientName)) {
                $warnings[] = 'اسم المريض داخل DICOM ('.$dicomName.') لا يطابق اسم الملف ('.($patientName ?? '').'). حُفظت الصور على هذا الملف دون تغيير بياناته.';
            }
        }

        $uids = [];

        foreach ($images as $image) {
            $uid = $image->dicom?->studyInstanceUid;

            if ($uid !== null) {
                $uids[$uid] = true;
            }
        }

        if (count($uids) > 1) {
            $warnings[] = 'الحزمة تحتوي أكثر من فحص DICOM. حُفظت كلها على هذه الدراسة وملف المريض.';
        }

        if ($skipped > 0) {
            $warnings[] = 'تُجاهل '.$skipped.' ملفاً خارج صور الفحص، مثل ملفات النظام أو DICOMDIR.';
        }

        return $warnings;
    }

    /**
     * @param  array<int, AcceptedImage>  $images
     */
    private function firstUid(array $images): ?string
    {
        foreach ($images as $image) {
            $uid = $image->dicom?->studyInstanceUid;

            if ($uid !== null) {
                return mb_substr($uid, 0, 190);
            }
        }

        return null;
    }

    /**
     * @param  array<int, AcceptedImage>  $images
     */
    private function firstAccession(array $images): ?string
    {
        foreach ($images as $image) {
            $accession = $image->dicom?->accessionNumber;

            if ($accession !== null) {
                return mb_substr($accession, 0, 100);
            }
        }

        return null;
    }

    private function ceiling(): int
    {
        try {
            $value = config('hms.documents.imaging.max_study_files', 2000);

            return is_numeric($value) ? (int) $value : 2000;
        } catch (\Throwable) {
            return 2000;
        }
    }
}
