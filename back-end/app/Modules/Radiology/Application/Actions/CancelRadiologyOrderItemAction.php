<?php

declare(strict_types=1);

namespace App\Modules\Radiology\Application\Actions;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Radiology\Domain\Enums\RadiologyItemStatus;
use App\Modules\Radiology\Domain\Events\RadiologyOrderItemCancelled;
use App\Modules\Radiology\Infrastructure\Persistence\Models\RadiologyOrderItemModel;

final class CancelRadiologyOrderItemAction
{
    public function execute(RadiologyOrderItemModel $item): RadiologyOrderItemModel
    {
        if ($item->status === RadiologyItemStatus::Cancelled) {
            throw BusinessRuleException::make('هذا الطلب ملغى مسبقاً.');
        }

        if ($item->status === RadiologyItemStatus::Reported) {
            throw BusinessRuleException::make('لا يمكن إلغاء الطلب بعد إرسال النتيجة.');
        }

        $item->forceFill(['status' => RadiologyItemStatus::Cancelled->value])->save();
        RadiologyOrderItemCancelled::dispatch($item);

        return $item;
    }
}
