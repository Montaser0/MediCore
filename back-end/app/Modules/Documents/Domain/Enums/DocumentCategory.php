<?php

declare(strict_types=1);

namespace App\Modules\Documents\Domain\Enums;

enum DocumentCategory: string
{
    case RadiologyImage = 'radiology_image';
    case RadiologyReport = 'radiology_report';
    case LabAttachment = 'lab_attachment';
    case MedicalReport = 'medical_report';
    case Prescription = 'prescription';
    case Consent = 'consent';
    case Identity = 'identity';
    case Referral = 'referral';
    case Insurance = 'insurance';
    case Invoice = 'invoice';
    case OperationRecord = 'operation_record';
    case DischargeSummary = 'discharge_summary';
    case Administrative = 'administrative';
    case Other = 'other';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isMedicalImage(): bool
    {
        return $this === self::RadiologyImage;
    }
}
