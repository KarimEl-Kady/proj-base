<?php

namespace App\Modules\Core\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders API exceptions in the same envelope as Controller::jsonResponse()
 * ({success, message, errors}). Registered in bootstrap/app.php.
 */
class Handler
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return null;
        }

        [$status, $message] = static::resolve($e);

        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($e instanceof ValidationException) {
            $payload['errors'] = $e->errors();
        }

        return response()->json($payload, $status);
    }

    /**
     * @return array{0: int, 1: string}
     */
    protected static function resolve(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [$e->status, collect($e->errors())->flatten()->first() ?? $e->getMessage()],
            $e instanceof ModelNotFoundException => [404, 'Resource not found.'],
            $e instanceof AuthenticationException => [401, 'Unauthenticated.'],
            $e instanceof AuthorizationException => [403, $e->getMessage() ?: 'This action is unauthorized.'],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getMessage() ?: 'Http Error'],
            default => [500, config('app.debug') ? ($e->getMessage() ?: 'Server Error') : 'Server Error'],
        };
    }
}
