<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Core\Support\Traits\BelongsToHospital;
use App\Modules\Billing\Domain\Enums\PaymentMethod;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PaymentModel extends BaseModel
{
    use Auditable;
    use BelongsToHospital;

    protected $table = 'payments';

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'float',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceModel::class, 'invoice_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'received_by');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(RefundModel::class, 'payment_id');
    }

    public function refundedAmount(): float
    {
        return (float) $this->refunds()->sum('amount');
    }

    public function netAmount(): float
    {
        return (float) $this->amount - $this->refundedAmount();
    }
}
