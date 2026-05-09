<?php

namespace App\Modules\Core\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function jsonResponse(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
        array $headers = []
    ): JsonResponse {
        return response()->json([
            'success' => $status >= 200 && $status < 300,
            'message' => $message,
            'data' => $data,
        ], $status, $headers);
    }

    protected function jsonError(
        string $message = 'Error',
        int $status = 400,
        mixed $errors = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
