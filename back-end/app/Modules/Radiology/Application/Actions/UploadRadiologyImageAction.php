<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Storage\FileReference;
use App\Core\Support\Contracts\DocumentService;
use App\Core\Support\Contracts\NotificationDispatcher;
use App\Modules\Radiology\Domain\Enums\RadiologyItemStatus;
use App\Modules\Radiology\Domain\Enums\StudyStatus;
use App\Modules\Radiology\Domain\Events\ImagesUploaded;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyStudyModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Images are stored through the shared Documents module (PRD §66: bytes on
 * disk, metadata in the database, access only through the authorised file
 * endpoint). Radiology just links them to the study.
 */
final class UploadRadiologyImageAction
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly ChangeRadiologyItemStatusAction $changeStatus,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, FileReference>
     */
    public function execute(RadiologyStudyModel $study, array $files): array
    {
        if ($files === []) {
            throw BusinessRuleException::make('اختر صورة واحدة على الأقل للرفع.');
        }

        $study->loadMissing(['radiologyOrder.order.encounter', 'orderItem.service']);

        $order = $study->radiologyOrder?->order;

        return DB::transaction(function () use ($study, $files, $order): array {
            $references = [];

            foreach ($files as $file) {
                $references[] = $this->documents->attach(
                    file: $file,
                    entityType: 'radiology_study',
                    entityId: $study->getKey(),
                    category: 'radiology_image',
                    patientId: $order?->encounter?->patient_id,
                    encounterId: $order?->encounter_id,
                );
            }

            $study->forceFill([
                'image_count' => $this->documents->countFor('radiology_study', $study->getKey(), 'radiology_image'),
                'status' => StudyStatus::Completed->value,
            ])->save();

            $item = $study->orderItem;

            if ($item !== null && $item->status->canTransitionTo(RadiologyItemStatus::Completed)) {
                $this->changeStatus->execute($item, RadiologyItemStatus::Completed);
            }

            ImagesUploaded::dispatch($study, count($references));

            // Images acquired: the radiologist can now report on the study.
            $this->notifications->toPermissionHolders(
                permission: 'radiology.reports.create',
                type: 'radiology.study_ready',
                title: 'الصور جاهزة لكتابة التقرير',
                message: ($item?->service?->name ?? 'Study').' '.$study->study_number.' has images available.',
                entityType: 'radiology_study',
                entityId: $study->getKey(),
                payload: ['image_count' => $study->image_count],
            );

            return $references;
        });
    }
}
