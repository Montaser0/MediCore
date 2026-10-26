<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PharmacyCounterTicketModel extends BaseModel
{
    use BelongsToHospital;

    protected $table = 'pharmacy_counter_tickets';

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientModel::class, 'patient_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'sent_by');
    }
}
