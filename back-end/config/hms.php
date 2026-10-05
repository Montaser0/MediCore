<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default hospital
    |--------------------------------------------------------------------------
    |
    | The system is designed multi-hospital from day one, but a single-hospital
    | deployment falls back to this record whenever the acting user has no
    | staff profile attached (for example the bootstrap administrator).
    |
    */

    'default_hospital_code' => env('HMS_DEFAULT_HOSPITAL_CODE', 'MAIN'),

    /*
    |--------------------------------------------------------------------------
    | Human readable numbering
    |--------------------------------------------------------------------------
    |
    | Every sequence is scoped per hospital and per year, producing values such
    | as MRN-2026-000154 or ENC-2026-00981.
    |
    */

    'numbering' => [
        'mrn' => ['prefix' => 'MRN', 'padding' => 6],
        'encounter' => ['prefix' => 'ENC', 'padding' => 5],
        'appointment' => ['prefix' => 'APT', 'padding' => 5],
        'order' => ['prefix' => 'ORD', 'padding' => 6],
        'lab_sample' => ['prefix' => 'LAB', 'padding' => 5],
        'radiology_study' => ['prefix' => 'STD', 'padding' => 5],
        'prescription' => ['prefix' => 'RX', 'padding' => 5],
        'dispense' => ['prefix' => 'DSP', 'padding' => 5],
        'admission' => ['prefix' => 'ADM', 'padding' => 5],
        'operation' => ['prefix' => 'OPR', 'padding' => 5],
        'invoice' => ['prefix' => 'INV', 'padding' => 6],
        'receipt' => ['prefix' => 'RCP', 'padding' => 6],
        'employee' => ['prefix' => 'EMP', 'padding' => 4],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documents and medical imaging
    |--------------------------------------------------------------------------
    |
    | Binary content never goes into MySQL. Only the metadata is stored in the
    | `files` table while the bytes live on the configured disk, which may be a
    | local path inside the hospital or an S3-compatible bucket.
    |
    */

    'documents' => [
        'disk' => env('HMS_DOCUMENTS_DISK', 'local'),
        'max_upload_kb' => (int) env('HMS_MAX_UPLOAD_KB', 51200),
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'dcm'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | When enabled, delivered work raises a charge on its own: the visit
    | consultation, a procedure, a completed order, a verified laboratory
    | result, a released imaging report, a dispense, a finished operation
    | and the bed nights at discharge. Closing the visit issues the invoice.
    |
    */

    'billing' => [
        'auto_charge' => (bool) env('HMS_AUTO_CHARGE', true),
        'tax_rate' => (float) env('HMS_TAX_RATE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    'inventory' => [
        'batch_strategy' => env('HMS_BATCH_STRATEGY', 'fefo'),
        'expiry_alert_days' => (int) env('HMS_EXPIRY_ALERT_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auditing
    |--------------------------------------------------------------------------
    |
    | Audit rows are append-only; the API exposes no delete route for them.
    |
    */

    'audit' => [
        'enabled' => (bool) env('HMS_AUDIT_ENABLED', true),
        'queue' => (bool) env('HMS_AUDIT_QUEUE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local pharmacy station
    |--------------------------------------------------------------------------
    |
    | Used by the Windows package. Paths are empty in development, and the
    | backup service falls back to storage/app/hms-backups.
    |
    */

    'local' => [
        'version' => env('HMS_APP_VERSION', '1.0.0'),
        'backup_dir' => env('HMS_BACKUP_DIR'),
        'mysqldump' => env('HMS_MYSQLDUMP'),
        'mysql' => env('HMS_MYSQL'),
        'control_script' => env('HMS_CONTROL_SCRIPT'),
        'backup_timezone' => env('HMS_BACKUP_TIMEZONE', 'Asia/Damascus'),
    ],
];
