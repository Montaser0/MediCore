<?php

declare(strict_types=1);

namespace App\Core\Support\Numbering;

enum SequenceKey: string
{
    case Mrn = 'mrn';
    case Encounter = 'encounter';
    case Appointment = 'appointment';
    case Order = 'order';
    case LabSample = 'lab_sample';
    case RadiologyStudy = 'radiology_study';
    case Prescription = 'prescription';
    case Dispense = 'dispense';
    case Admission = 'admission';
    case Operation = 'operation';
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case Employee = 'employee';

    public function prefix(): string
    {
        return (string) config("hms.numbering.{$this->value}.prefix", strtoupper($this->value));
    }

    public function padding(): int
    {
        return (int) config("hms.numbering.{$this->value}.padding", 6);
    }
}
