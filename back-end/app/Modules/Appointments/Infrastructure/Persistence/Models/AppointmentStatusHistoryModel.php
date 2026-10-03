<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;

final class AppointmentStatusHistoryModel extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'appointment_status_history';

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
