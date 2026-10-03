<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Pharmacy\Domain\Enums\PrescriptionStatus;
use App\Modules\Pharmacy\Infrastructure\Persistence\Models\PrescriptionModel;

final class ChangePrescriptionStatusAction
{
    public function execute(PrescriptionModel $prescription, PrescriptionStatus $target, ?string $reason = null): PrescriptionModel
    {
        if ($prescription->status === $target) {
            return $prescription;
        }

        if (! $prescription->status->canTransitionTo($target)) {
            throw BusinessRuleException::make(
                "لا يمكن تنفيذ هذا الإجراء والوصفة {$prescription->prescription_number} حالتها «{$prescription->status->label()}».",
                [
                    'allowed' => array_map(
                        static fn (PrescriptionStatus $status) => $status->value,
                        $prescription->status->allowedTransitions(),
                    ),
                ],
            );
        }

        $prescription->forceFill([
            'status' => $target->value,
            'cancellation_reason' => $target === PrescriptionStatus::Cancelled ? $reason : $prescription->cancellation_reason,
        ])->save();

        return $prescription;
    }
}
