<?php

declare(strict_types=1);

namespace App\Modules\Settings\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;

/**
 * @property string $group
 * @property string $key
 * @property mixed $value
 */
final class SettingModel extends BaseModel
{
    protected $table = 'settings';

    protected $casts = [
        'value' => 'array',
    ];
}
