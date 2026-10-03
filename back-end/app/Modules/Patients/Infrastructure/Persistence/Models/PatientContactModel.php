<?php

declare(strict_types=1);

namespace App\Modules\Patients\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $patient_id
 * @property bool $is_emergency_contact
 */
final class PatientContactModel extends BaseModel
{
    protected $table = 'patient_contacts';

    protected $casts = [
        'is_emergency_contact' => 'boolean',
    ];

    /**
     * @return BelongsTo<PatientModel, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientModel::class, 'patient_id');
    }
}
