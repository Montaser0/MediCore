<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Enums;

/**
 * The clinical events worth interrupting someone for.
 */
enum NotificationType: string
{
    case NewLabOrder = 'lab.order.created';
    case LabResultReady = 'lab.result.ready';
    case LabResultVerified = 'lab.result.verified';
    case CriticalLabResult = 'lab.result.critical';

    case NewRadiologyOrder = 'radiology.order.created';
    case RadiologyImagesUploaded = 'radiology.images.uploaded';
    case RadiologyReportReady = 'radiology.report.ready';

    case NewPrescription = 'pharmacy.prescription.created';
    case MedicationDispensed = 'pharmacy.medication.dispensed';

    case LowStock = 'inventory.stock.low';
    case ExpiringStock = 'inventory.stock.expiring';

    case PatientTransferred = 'encounter.transferred';
    case PatientAdmitted = 'inpatient.admitted';
    case PatientDischarged = 'inpatient.discharged';

    case InvoiceIssued = 'billing.invoice.issued';
    case PaymentReceived = 'billing.payment.received';

    /**
     * Critical results are the only ones the frontend renders as a blocking
     * alert rather than a bell badge.
     */
    public function isCritical(): bool
    {
        return $this === self::CriticalLabResult;
    }
}
