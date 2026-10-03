<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API entry point
|--------------------------------------------------------------------------
|
| Feature routes are not declared here. Each module owns its own route file
| under app/Modules/<Module>/Presentation/Routes/api.php and registers it
| through its service provider, so adding a department never means editing a
| shared file.
|
*/

Route::get('/version', fn () => [
    'data' => [
        'application' => config('app.name'),
        'api_version' => 'v1',
        'timezone' => config('app.timezone'),
    ],
]);
