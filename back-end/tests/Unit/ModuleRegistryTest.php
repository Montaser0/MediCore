<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Modules\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class ModuleRegistryTest extends TestCase
{
    public function test_clinical_editions_share_the_patient_shell_and_keep_their_department(): void
    {
        $clinic = new ModuleRegistry('clinic');
        $laboratory = new ModuleRegistry('laboratory');
        $radiology = new ModuleRegistry('radiology');

        foreach ([$clinic, $laboratory, $radiology] as $registry) {
            $this->assertTrue($registry->enabled('Patients'));
            $this->assertTrue($registry->enabled('Appointments'));
            $this->assertTrue($registry->enabled('Encounters'));
            $this->assertTrue($registry->enabled('Clinical'));
            $this->assertTrue($registry->enabled('Documents'));
            $this->assertTrue($registry->enabled('Billing'));
            $this->assertFalse($registry->enabled('Pharmacy'));
            $this->assertFalse($registry->enabled('Inpatient'));
            $this->assertContains('patients', $registry->navigation());
            $this->assertContains('appointments', $registry->navigation());
            $this->assertContains('booking-link', $registry->navigation());
            $this->assertContains('billing', $registry->navigation());
            $this->assertContains('reports', $registry->navigation());
            $this->assertTrue($registry->enabled('Reports'));
        }

        $this->assertTrue($laboratory->enabled('Laboratory'));
        $this->assertTrue($laboratory->enabled('Orders'));
        $this->assertFalse($laboratory->enabled('Radiology'));
        $this->assertContains('laboratory', $laboratory->navigation());

        $this->assertTrue($radiology->enabled('Radiology'));
        $this->assertTrue($radiology->enabled('Orders'));
        $this->assertFalse($radiology->enabled('Laboratory'));
        $this->assertContains('radiology', $radiology->navigation());
        $this->assertContains('radiology-services', $radiology->navigation());
        $this->assertFalse($clinic->enabled('Laboratory'));
        $this->assertFalse($clinic->enabled('Orders'));
    }

    public function test_pharmacy_edition_is_the_point_of_sale_only(): void
    {
        $registry = new ModuleRegistry('pharmacy');

        $this->assertTrue($registry->enabled('Pharmacy'));
        $this->assertTrue($registry->feature('pharmacy.pos'));
        $this->assertFalse($registry->feature('pharmacy.prescriptions'));
        $this->assertFalse($registry->enabled('Patients'));
        $this->assertFalse($registry->enabled('Appointments'));
        $this->assertFalse($registry->enabled('Inventory'));
        $this->assertTrue($registry->enabled('Reports'));
        $this->assertSame(
            ['home', 'pharmacy-catalog', 'pharmacy-sale', 'pharmacy-return', 'pharmacy-debts', 'pharmacy-warehouses', 'pharmacy-stocktake', 'reports'],
            $registry->navigation(),
        );
    }

    public function test_hospital_edition_keeps_every_module_and_prescriptions(): void
    {
        $registry = new ModuleRegistry('hospital');

        $this->assertTrue($registry->enabled('Laboratory'));
        $this->assertTrue($registry->enabled('Inpatient'));
        $this->assertTrue($registry->enabled('Billing'));
        $this->assertTrue($registry->feature('pharmacy.prescriptions'));
        $this->assertFalse($registry->feature('pharmacy.pos'));
        $this->assertSame(['*'], $registry->navigation());
    }

    public function test_unknown_edition_falls_back_to_the_hospital(): void
    {
        $registry = new ModuleRegistry('dental');

        $this->assertSame('hospital', $registry->edition());
        $this->assertTrue($registry->enabled('Nursing'));
    }
}
