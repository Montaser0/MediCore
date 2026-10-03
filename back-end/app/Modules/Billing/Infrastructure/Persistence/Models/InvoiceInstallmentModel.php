<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use App\Core\Persistence\BaseModel;
use App\Modules\Billing\Domain\Enums\InstallmentStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InvoiceInstallmentModel extends BaseModel
{
    protected $table = 'invoice_installments';

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'amount' => 'float',
            'due_date' => 'date',
            'paid_amount' => 'float',
            'status' => InstallmentStatus::class,
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InvoiceInstallmentPlanModel::class, 'plan_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceModel::class, 'invoice_id');
    }

    /**
     * A scheduled installment whose due date has passed is overdue. Today
     * itself is still on time.
     */
    public function displayStatus(): string
    {
        if ($this->status !== InstallmentStatus::Paid
            && $this->due_date !== null
            && $this->due_date->copy()->startOfDay()->lt(now()->startOfDay())
        ) {
            return 'overdue';
        }

        return $this->status->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        $status = $this->displayStatus();

        return [
            'id' => $this->getKey(),
            'sequence' => $this->sequence,
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'remaining' => round(max(0, (float) $this->amount - (float) $this->paid_amount), 2),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $status,
            'status_label' => $status === 'overdue' ? 'متأخر' : $this->status->label(),
        ];
    }
}
