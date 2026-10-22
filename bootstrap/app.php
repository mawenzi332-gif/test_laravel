<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/v1', 'api/v1/*')) {
                return null;
            }

            $status = match (true) {
                $exception instanceof ValidationException => $exception->status,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException,
                $exception instanceof NotFoundHttpException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            $validationErrors = $exception instanceof ValidationException ? $exception->errors() : [];
            $failedLogin = $request->routeIs('login.store')
                && ($validationErrors['email'][0] ?? null) === trans('auth.failed');

            [$code, $message, $details] = match (true) {
                $failedLogin => [
                    'AUTHENTICATION_FAILED',
                    'The provided credentials are incorrect.',
                    (object) [],
                ],
                $exception instanceof ValidationException => [
                    'VALIDATION_ERROR',
                    'The submitted data is invalid.',
                    $exception->errors(),
                ],
                $exception instanceof AuthenticationException => [
                    'UNAUTHENTICATED',
                    'Authentication is required.',
                    (object) [],
                ],
                $exception instanceof AuthorizationException => [
                    'FORBIDDEN',
                    'You are not allowed to perform this action.',
                    (object) [],
                ],
                $exception instanceof ModelNotFoundException,
                $exception instanceof NotFoundHttpException => [
                    'NOT_FOUND',
                    'The requested resource was not found.',
                    (object) [],
                ],
                $status === 403 => ['FORBIDDEN', 'You are not allowed to perform this action.', (object) []],
                $status === 404 => ['NOT_FOUND', 'The requested resource was not found.', (object) []],
                $status === 429 => ['RATE_LIMITED', 'Too many requests.', (object) []],
                default => ['INTERNAL_ERROR', 'An unexpected error occurred.', (object) []],
            };

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => $message,
                    'details' => $details,
                ],
            ], $status);
        });
    })->create();
