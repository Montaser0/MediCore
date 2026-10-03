<?php

declare(strict_types=1);

namespace App\Modules\Patients\Domain\Exceptions;

use App\Core\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

final class PatientNotFoundException extends DomainException
{
    protected int $status = Response::HTTP_NOT_FOUND;

    protected string $errorCode = 'patient_not_found';

    public static function byId(int $id): self
    {
        return new self('ملف المريض غير موجود.', ['patient_id' => $id]);
    }

    public static function byMrn(string $mrn): self
    {
        return new self("لا يوجد ملف مريض بالرقم {$mrn}.", ['mrn' => $mrn]);
    }
}
