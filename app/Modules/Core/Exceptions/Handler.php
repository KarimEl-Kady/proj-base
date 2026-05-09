<?php

namespace App\Modules\Core\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected function renderJson(Request $request, Throwable $e): JsonResponse
    {
        $status = $this->resolveStatusCode($e);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage() ?: 'Server Error',
            'errors' => $e instanceof ValidationException ? $e->errors() : null,
        ], $status);
    }

    protected function resolveStatusCode(Throwable $e): int
    {
        if ($e instanceof HttpException) {
            return $e->getStatusCode();
        }

        if ($e instanceof ValidationException) {
            return $e->status;
        }

        if ($this->isHttpException($e)) {
            return $e->getStatusCode();
        }

        return 500;
    }
}
