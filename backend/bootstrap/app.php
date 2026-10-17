<?php

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UniqueConstraintErrors;
use App\Http\Controllers\ReadinessController;
use App\Http\Middleware\AuditDeniedRequests;
use App\Http\Middleware\EnsureActiveTenant;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\LimitRequestBody;
use App\Http\Middleware\ValidateApiPayload;
use App\Http\Middleware\ValidateRouteIdentifiers;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/ready', ReadinessController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        $middleware->appendToGroup('api', ValidateRouteIdentifiers::class);
        $middleware->prependToGroup('api', AuditDeniedRequests::class);
        $middleware->prependToGroup('api', ValidateApiPayload::class);
        $middleware->prependToGroup('api', LimitRequestBody::class);
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
        $exceptions->render(function (UniqueConstraintViolationException $exception, Request $request): ?JsonResponse {
            $errors = UniqueConstraintErrors::validationErrors($exception);
            if (! $request->is('api', 'api/*') || $errors === null) {
                return null;
            }

            return response()->json(['success' => false, 'data' => null,
                'message' => 'Erro de validação', 'errors' => $errors], 422);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request): ?JsonResponse {
            if (! $request->is('api', 'api/*')) {
                return null;
            }
            $status = $exception->getStatusCode();
            $message = match ($status) {
                400 => 'Requisição inválida',
                403 => 'Você não tem permissão para esta ação',
                404 => 'Recurso não encontrado',
                405 => 'Método não permitido',
                413 => 'O corpo da requisição excede o limite permitido.',
                429 => 'Muitas tentativas. Tente novamente mais tarde.',
                default => $status >= 500 ? 'Serviço temporariamente indisponível' : 'Requisição recusada',
            };

            return response()->json(['success' => false, 'data' => null,
                'message' => $message, 'errors' => null], $status, $exception->getHeaders());
        });

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

        $exceptions->render(function (Throwable $exception, Request $request): ?JsonResponse {
            // Let the framework return deliberate responses, including rate-limit callbacks.
            if ($exception instanceof HttpResponseException) {
                return null;
            }

            return $request->is('api', 'api/*') ? response()->json([
                'success' => false, 'data' => null, 'message' => 'Erro interno do servidor', 'errors' => null,
            ], 500) : null;
        });
    })->create();
