<?php

declare(strict_types=1);

namespace App\Modules\Laboratory\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Laboratory\Domain\Enums\LabOrderItemStatus;
use App\Modules\Laboratory\Domain\Enums\SampleStatus;
use App\Modules\Laboratory\Infrastructure\Persistence\Models\LabSampleModel;
use Illuminate\Support\Facades\DB;

/**
 * Sample reception inside the laboratory, and rejection of an unusable one
 * (wrong tube, haemolysed, unlabelled), which sends the test back to
 * "awaiting sample" so a fresh draw can be taken.
 */
final class ReceiveSampleAction
{
    public function __construct(private readonly ChangeLabItemStatusAction $changeStatus) {}

    public function receive(LabSampleModel $sample): LabSampleModel
    {
        $this->guardTransition($sample, SampleStatus::Received);

        return DB::transaction(function () use ($sample): LabSampleModel {
            $sample->forceFill([
                'status' => SampleStatus::Received->value,
                'received_at' => now(),
            ])->save();

            $item = $sample->orderItem;

            if ($item !== null && $item->status === LabOrderItemStatus::SampleCollected) {
                $this->changeStatus->execute($item, LabOrderItemStatus::Processing);
            }

            return $sample;
        });
    }

    public function reject(LabSampleModel $sample, string $reason): LabSampleModel
    {
        $this->guardTransition($sample, SampleStatus::Rejected);

        $sample->forceFill([
            'status' => SampleStatus::Rejected->value,
            'rejection_reason' => $reason,
        ])->save();

        return $sample;
    }

    private function guardTransition(LabSampleModel $sample, SampleStatus $target): void
    {
        if (! $sample->status->canTransitionTo($target)) {
            throw BusinessRuleException::make(
                "لا يمكن تنفيذ هذا الإجراء والعينة {$sample->sample_number} حالتها «{$sample->status->label()}».",
            );
        }
    }
}
