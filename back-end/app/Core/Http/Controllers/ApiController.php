<?php

declare(strict_types=1);

namespace App\Core\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

abstract class ApiController extends Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;

    protected function perPage(int $default = 25, int $max = 200): int
    {
        $requested = (int) request()->integer('per_page', $default);

        return max(1, min($requested, $max));
    }
}
