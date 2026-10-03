<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InvoiceInstallmentPlanModel extends BaseModel
{
    protected $table = 'invoice_installment_plans';

    protected function casts(): array
    {
        return [
            'baseline_paid' => 'float',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceModel::class, 'invoice_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallmentModel::class, 'plan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }
}
