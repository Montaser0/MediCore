<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment edition
    |--------------------------------------------------------------------------
    |
    | An edition is a preset of modules. The registry, not the string itself,
    | decides which routes, listeners and screens exist.
    |
    | hospital    full hospital
    | clinic      patients, appointments, file, account, booking
    | laboratory  clinic shell plus the laboratory
    | radiology   clinic shell plus radiology
    | pharmacy    barcode point of sale only
    |
    */

    'edition' => env('HMS_EDITION', 'hospital'),

];
