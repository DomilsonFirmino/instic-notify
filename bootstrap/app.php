<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorizedException;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('api/V1')
                ->middleware('api')
                ->group(base_path('routes/api_V1.php'));
            Route::prefix('api/auth')
                ->middleware('api')
                ->group(base_path('routes/auth.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure JSON response for unauthenticated requests (e.g., logout without token)
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Token de autenticação não fornecido ou inválido.',
                        'details' => [],
                    ],
                ], 401);
            }
            return null; // fall back to default handler for non-JSON requests
        });

        // Standardized JSON for Eloquent model not found
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Recurso não encontrado.',
                        'details' => [
                            'model' => $e->getModel(),
                            'ids' => method_exists($e, 'getIds') ? $e->getIds() : [],
                        ],
                    ],
                ], 404);
            }
            return null; // fall back to default handler for non-JSON requests
        });

        // Standardized JSON for HTTP 404 (route/model binding converted exceptions)
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson()) {
                $details = [];

                // Include requested path and parameters for extra context
                $details['path'] = $request->path();
                $details['params'] = $request->route()?->parameters() ?? [];

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Recurso não encontrado.',
                        'details' => $details,
                    ],
                ], 404);
            }
            return null; // fall back to default handler for non-JSON requests
        });

            // Standardized JSON for authorization failures (403)
            $exceptions->render(function (AuthorizationException $e, $request) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'FORBIDDEN',
                            'message' => $e->getMessage() ?: 'Sem permissão para executar esta ação.',
                            'details' => [
                                'path' => $request->path(),
                                'params' => $request->route()?->parameters() ?? [],
                                'user_id' => optional($request->user())->id,
                            ],
                        ],
                    ], 403);
                }
                return null; // fall back to default handler for non-JSON requests
            });

            // Handle Spatie\Permission UnauthorizedException (e.g., missing required roles/permissions)
            $exceptions->render(function (SpatieUnauthorizedException $e, $request) {
                if ($request->expectsJson()) {
                    // Map Spatie's exception to a consistent 403 JSON structure
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'FORBIDDEN',
                            'message' => $e->getMessage() ?: 'Usuario não possui as permissões necessárias.',
                            'details' => [
                                'path' => $request->path(),
                                'params' => $request->route()?->parameters() ?? [],
                                'user_id' => optional($request->user())->id,
                            ],
                        ],
                    ], 403);
                }
                return null; // fall back to default handler for non-JSON requests
            });

        // Handle database connection errors (PDOException)
        $exceptions->render(function (PDOException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DB_CONNECTION_ERROR',
                        'message' => 'Database is not available',
                        'details' => [
                            'exception' => class_basename($e),
                            'message' => $e->getMessage(),
                        ],
                    ],
                ], 503);
            }
            return null; // fall back to default handler for non-JSON requests
        });
    })->create();
