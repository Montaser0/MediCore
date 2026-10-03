<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Application\Actions;

use App\Core\Auth\CurrentUser;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Appointments\Domain\Enums\AppointmentStatus;
use App\Modules\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Illuminate\Support\Facades\DB;

final class ChangeAppointmentStatusAction
{
    public function __construct(private readonly CurrentUser $currentUser) {}

    public function execute(AppointmentModel $appointment, AppointmentStatus $target, ?string $note = null): AppointmentModel
    {
        $current = $appointment->status;

        if ($current === $target) {
            return $appointment;
        }

        if (! $current->canTransitionTo($target)) {
            throw BusinessRuleException::make($this->refusal($current, $target), [
                'from' => $current->value,
                'to' => $target->value,
            ]);
        }

        return DB::transaction(function () use ($appointment, $current, $target, $note): AppointmentModel {
            $appointment->forceFill(['status' => $target->value])->save();

            $appointment->statusHistory()->create([
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by' => $this->currentUser->id(),
                'note' => $note,
            ]);

            return $appointment;
        });
    }

    private function refusal(AppointmentStatus $current, AppointmentStatus $target): string
    {
        $state = $current->label();

        return match ($target) {
            AppointmentStatus::Confirmed => match ($current) {
                AppointmentStatus::CheckedIn => 'المريض حضر مسبقاً، والتأكيد يكون قبل الحضور.',
                AppointmentStatus::Completed => 'الزيارة انتهت، ولا يمكن تأكيد الموعد بعدها.',
                AppointmentStatus::Cancelled => 'الموعد ملغى، ولا يمكن تأكيده.',
                AppointmentStatus::NoShow => 'سُجّل أن المريض لم يحضر، ولا يمكن تأكيد الموعد.',
                default => "لا يمكن تأكيد موعد حالته {$state}.",
            },
            AppointmentStatus::CheckedIn => match ($current) {
                AppointmentStatus::Completed => 'زيارة هذا الموعد انتهت.',
                AppointmentStatus::Cancelled => 'الموعد ملغى. أنشئ موعداً جديداً إذا حضر المريض.',
                AppointmentStatus::NoShow => 'سُجّل أن المريض لم يحضر. أنشئ موعداً جديداً لتسجيل الحضور.',
                default => "لا يمكن تسجيل حضور موعد حالته {$state}.",
            },
            AppointmentStatus::Cancelled => match ($current) {
                AppointmentStatus::Completed => 'الزيارة انتهت، ولا يُلغى الموعد بعد اكتمالها.',
                AppointmentStatus::NoShow => 'سُجّل أن المريض لم يحضر، والإلغاء لم يعد متاحاً.',
                default => "لا يمكن إلغاء موعد حالته {$state}.",
            },
            AppointmentStatus::NoShow => match ($current) {
                AppointmentStatus::CheckedIn => 'المريض حضر مسبقاً، ولا يمكن تسجيل أنه لم يحضر.',
                AppointmentStatus::Completed => 'الزيارة انتهت، ولا يمكن تسجيل الغياب.',
                AppointmentStatus::Cancelled => 'الموعد ملغى مسبقاً.',
                default => "لا يمكن تسجيل الغياب لموعد حالته {$state}.",
            },
            AppointmentStatus::Completed => "اكتمال الموعد يتم عند إنهاء الزيارة، وحالته الآن {$state}.",
            default => "لا يمكن تنفيذ هذا الإجراء والموعد حالته {$state}.",
        };
    }
}
