<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum AssetCategory: string
{
    case Laptop = 'laptop';
    case Desktop = 'desktop';
    case Monitor = 'monitor';
    case Printer = 'printer';
    case BarcodeReader = 'barcode_reader';
    case Mobile = 'mobile';
    case Camera = 'camera';

    public function label(): string
    {
        return match ($this) {
            self::Laptop => 'لابتوب',
            self::Desktop => 'كمبيوتر',
            self::Monitor => 'شاشة',
            self::Printer => 'طابعة',
            self::BarcodeReader => 'قارئ باركود',
            self::Mobile => 'موبايل',
            self::Camera => 'كاميرا',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $category): array => ['value' => $category->value, 'label' => $category->label()],
            self::cases(),
        );
    }
}
