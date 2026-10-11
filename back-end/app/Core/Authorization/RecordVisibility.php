<?php

declare(strict_types=1);

namespace App\Core\Authorization;

use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Which patient-file sections and timeline events the signed-in user may see.
 * A section stays hidden unless the account holds one of its permissions.
 */
final class RecordVisibility
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function sections(): array
    {
        return [
            'clinical' => [Permissions::CLINICAL_NOTES_VIEW, Permissions::CLINICAL_VITALS_VIEW, Permissions::CLINICAL_DIAGNOSIS_VIEW],
            'orders' => [Permissions::ORDERS_VIEW],
            'laboratory' => [Permissions::LABORATORY_ORDERS_VIEW],
            'radiology' => [Permissions::RADIOLOGY_ORDERS_VIEW],
            'pharmacy' => [Permissions::PHARMACY_PRESCRIPTIONS_VIEW],
            'nursing' => [Permissions::NURSING_NOTES_VIEW],
            'admissions' => [Permissions::INPATIENT_VIEW],
            'operations' => [Permissions::OPERATIONS_VIEW],
            'billing' => [Permissions::BILLING_INVOICES_VIEW, Permissions::BILLING_CHARGES_VIEW],
            'documents' => [Permissions::DOCUMENTS_VIEW],
            'encounters' => [Permissions::ENCOUNTERS_VIEW],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function events(): array
    {
        return [
            'billing.' => [Permissions::BILLING_INVOICES_VIEW, Permissions::BILLING_CHARGES_VIEW],
            'laboratory.' => [Permissions::LABORATORY_ORDERS_VIEW],
            'radiology.' => [Permissions::RADIOLOGY_ORDERS_VIEW],
            'pharmacy.' => [Permissions::PHARMACY_PRESCRIPTIONS_VIEW],
            'nursing.' => [Permissions::NURSING_NOTES_VIEW],
            'inpatient.' => [Permissions::INPATIENT_VIEW],
            'operation.' => [Permissions::OPERATIONS_VIEW],
            'clinical.' => [Permissions::CLINICAL_NOTES_VIEW, Permissions::CLINICAL_VITALS_VIEW, Permissions::CLINICAL_DIAGNOSIS_VIEW],
            'order.' => [Permissions::ORDERS_VIEW],
        ];
    }

    public static function sectionVisible(string $section): bool
    {
        $permissions = self::sections()[$section] ?? null;

        return $permissions === null || self::allowed($permissions);
    }

    /**
     * @return array<int, string>
     */
    public static function hiddenEventPrefixes(): array
    {
        $hidden = [];

        foreach (self::events() as $prefix => $permissions) {
            if (! self::allowed($permissions)) {
                $hidden[] = $prefix;
            }
        }

        return $hidden;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private static function allowed(array $permissions): bool
    {
        $user = auth()->user();

        if (! $user instanceof Authorizable) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
