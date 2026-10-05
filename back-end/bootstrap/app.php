<?php

declare(strict_types=1);

use App\Core\Exceptions\DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

if (! function_exists('arabicApiMessage')) {
    function arabicApiMessage(string $message, int $status): string
    {
        if ($message !== '' && preg_match('/\p{Arabic}/u', $message) === 1) {
            return $message;
        }

        return match ($status) {
            401 => 'يلزم تسجيل الدخول.',
            403 => 'ليست لديك صلاحية لهذا الإجراء.',
            404 => 'السجل المطلوب غير موجود.',
            405 => 'هذا الإجراء غير متاح.',
            409 => 'هذا الإجراء يتعارض مع الحالة الحالية.',
            419 => 'انتهت صلاحية الصفحة. حدّثها ثم أعد المحاولة.',
            422 => 'البيانات المدخلة غير صالحة.',
            429 => 'محاولات كثيرة. انتظر قليلاً ثم أعد المحاولة.',
            default => $status >= 500 ? 'حدث خطأ في الخادم.' : 'تعذر تنفيذ الطلب.',
        };
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // The Next.js client talks to the API with Sanctum bearer tokens, so
        // the api group stays stateless.
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Everything under /api answers with JSON, including auth failures.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (DomainException $e, Request $request) {
            return $e->render($request);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'السجل المطلوب غير موجود.',
                'error' => 'resource_not_found',
            ], 404);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => arabicApiMessage($e->getMessage(), 403),
                'error' => 'unauthorized_action',
            ], 403);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'يلزم تسجيل الدخول.',
                'error' => 'unauthenticated',
            ], 401);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $errors = $e->errors();
            $first = collect($errors)->flatten()->first();

            return response()->json([
                'message' => is_string($first) && $first !== ''
                    ? arabicApiMessage($first, 422)
                    : 'البيانات المدخلة غير صالحة.',
                'error' => 'validation_failed',
                'errors' => $errors,
            ], 422);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => arabicApiMessage($e->getMessage(), $e->getStatusCode()),
                'error' => 'http_error',
            ], $e->getStatusCode());
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'حدث خطأ غير متوقع. حاول مرة أخرى.',
                'error' => 'server_error',
            ], 500);
        });
    })->create();
