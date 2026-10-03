<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Core\Support\Traits\Auditable;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RefundModel extends BaseModel
{
    use Auditable;

    protected $table = 'refunds';

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'refunded_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentModel::class, 'payment_id');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'refunded_by');
    }
}
