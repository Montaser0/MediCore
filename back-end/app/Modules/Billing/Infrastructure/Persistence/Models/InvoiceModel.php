<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Encounters\Infrastructure\Persistence\Models\EncounterModel;
use App\Modules\Hospital\Infrastructure\Persistence\Models\HospitalModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Patients\Infrastructure\Persistence\Models\PatientModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class InvoiceModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'invoices';

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => 'float',
            'discount' => 'float',
            'tax' => 'float',
            'total' => 'float',
            'paid_amount' => 'float',
            'balance' => 'float',
            'issued_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientModel::class, 'patient_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(HospitalModel::class, 'hospital_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(EncounterModel::class, 'encounter_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItemModel::class, 'invoice_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(ChargeModel::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentModel::class, 'invoice_id');
    }

    public function installmentPlan(): HasOne
    {
        return $this->hasOne(InvoiceInstallmentPlanModel::class, 'invoice_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallmentModel::class, 'invoice_id')->orderBy('sequence');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyPaid->value,
        ])->where('balance', '>', 0);
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->balance <= 0.009;
    }
}
