<?php

declare(strict_types=1);

namespace App\Core\Modules;

/**
 * The single answer to "is this module on for this install?".
 * Editions are presets. Screens, routes and permissions all ask here.
 */
final class ModuleRegistry
{
    /**
     * Login, the facility record, settings storage and the audit writer.
     * Every edition needs these or patient and pharmacy actions fail closed.
     *
     * @var array<int, string>
     */
    private const CORE = ['Identity', 'Hospital', 'Settings', 'Audit'];

    /**
     * @var array<int, string>
     */
    private const ALL = [
        'Identity',
        'Hospital',
        'Departments',
        'Settings',
        'Audit',
        'Notifications',
        'Documents',
        'Patients',
        'Appointments',
        'Encounters',
        'Clinical',
        'Orders',
        'Laboratory',
        'Radiology',
        'Pharmacy',
        'Inventory',
        'Inpatient',
        'Nursing',
        'Operations',
        'Billing',
        'Reports',
    ];

    /**
     * Shared pages for a clinic, a laboratory and a radiology office:
     * patients, the medical file, appointments, the booking link and the account.
     *
     * @var array<int, string>
     */
    private const CLINICAL = [
        'Departments',
        'Documents',
        'Notifications',
        'Patients',
        'Appointments',
        'Encounters',
        'Clinical',
        'Billing',
        'Reports',
    ];

    /**
     * A module that cannot run unless another module is on.
     *
     * @var array<string, array<int, string>>
     */
    private const REQUIRES = [
        'Laboratory' => ['Orders'],
        'Radiology' => ['Orders'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const EDITIONS = [
        'hospital' => ['*'],
        'clinic' => self::CLINICAL,
        'laboratory' => [...self::CLINICAL, 'Orders', 'Laboratory'],
        'radiology' => [...self::CLINICAL, 'Orders', 'Radiology'],
        'pharmacy' => ['Pharmacy', 'Reports'],
    ];

    /**
     * Navigation ids, matched to front-end/src/config/navigation.ts.
     * "*" keeps the full hospital menu.
     *
     * @var array<string, array<int, string>>
     */
    private const NAVIGATION = [
        'hospital' => ['*'],
        'clinic' => ['home', 'patients', 'appointments', 'booking-link', 'billing', 'departments', 'staff', 'reports'],
        'laboratory' => ['home', 'patients', 'appointments', 'booking-link', 'billing', 'departments', 'staff', 'laboratory', 'reports'],
        'radiology' => ['home', 'patients', 'appointments', 'booking-link', 'billing', 'departments', 'staff', 'radiology', 'reports'],
        'pharmacy' => ['home', 'pharmacy-catalog', 'pharmacy-sale', 'pharmacy-return', 'pharmacy-debts', 'pharmacy-warehouses', 'pharmacy-stocktake', 'reports'],
    ];

    /**
     * Features split one code module into product surfaces.
     * The pharmacy module serves prescriptions in a hospital and a till in a pharmacy.
     *
     * @var array<string, array<int, string>>
     */
    private const FEATURES = [
        'pharmacy.prescriptions' => ['hospital'],
        'pharmacy.pos' => ['pharmacy'],
    ];

    private string $edition;

    public function __construct(?string $edition = null)
    {
        $requested = $edition ?? 'hospital';
        $this->edition = array_key_exists($requested, self::EDITIONS) ? $requested : 'hospital';
    }

    public function edition(): string
    {
        return $this->edition;
    }

    public function enabled(string $module): bool
    {
        return in_array($module, $this->modules(), true);
    }

    public function feature(string $feature): bool
    {
        $editions = self::FEATURES[$feature] ?? [];

        return in_array($this->edition, $editions, true);
    }

    /**
     * @return array<int, string>
     */
    public function modules(): array
    {
        $selected = self::EDITIONS[$this->edition];
        $names = $selected === ['*']
            ? self::ALL
            : array_values(array_unique([...self::CORE, ...$selected]));

        $expanded = $names;
        foreach ($names as $name) {
            foreach (self::REQUIRES[$name] ?? [] as $dependency) {
                $expanded[] = $dependency;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @return array<int, string>
     */
    public function navigation(): array
    {
        return self::NAVIGATION[$this->edition];
    }

    /**
     * @return array<int, string>
     */
    public function features(): array
    {
        return array_values(array_filter(
            array_keys(self::FEATURES),
            fn (string $feature): bool => $this->feature($feature),
        ));
    }

    /**
     * @return array{edition: string, modules: array<int, string>, features: array<int, string>, navigation: array<int, string>}
     */
    public function payload(): array
    {
        return [
            'edition' => $this->edition(),
            'modules' => $this->modules(),
            'features' => $this->features(),
            'navigation' => $this->navigation(),
        ];
    }
}
