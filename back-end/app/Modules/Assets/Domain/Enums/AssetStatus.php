<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum AssetStatus: string
{
    case InStock = 'in_stock';
    case Assigned = 'assigned';
    case Maintenance = 'maintenance';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'في المستودع',
            self::Assigned => 'مسلّم',
            self::Maintenance => 'صيانة',
            self::Retired => 'خارج الخدمة',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
