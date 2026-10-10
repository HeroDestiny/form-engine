<?php

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Http\Middleware\AuditDeniedRequests;
use App\Http\Middleware\EnsureActiveTenant;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', AuditDeniedRequests::class);
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->is('api/*')) {
                return null;
            }

            return '/';
        });

        $middleware->alias([
            'tenant.active' => EnsureActiveTenant::class,
            'role' => EnsureUserRole::class,
            'tenant.access' => EnsureTenantAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (BusinessException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match ($exception->reason) {
                'invalid_credentials' => 401,
                'forbidden' => 403,
                'form_unavailable', 'draft_exists', 'unpublished_version', 'field_removal_forbidden' => 400,
                default => 422,
            };

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $exception->getMessage(),
                'errors' => $exception->errors,
            ], $status);
        });

        $exceptions->render(function (NotFoundException $exception, Request $request): ?JsonResponse {
            return $request->is('api/*') ? response()->json([
                'success' => false,
                'data' => null,
                'message' => $exception->getMessage(),
                'errors' => null,
            ], 404) : null;
        });

        $exceptions->render(function (ValidationException $exception, Request $request): ?JsonResponse {
            return $request->is('api/*') ? response()->json([
                'success' => false,
                'data' => null,
                'message' => $request->is('api/auth/login') ? 'Dados invalidos' : 'Erro de validação',
                'errors' => $exception->errors(),
            ], 422) : null;
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request): JsonResponse {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Não autenticado',
                'errors' => null,
            ], 401);
        });
    })->create();
