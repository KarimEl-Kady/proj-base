<?php

namespace Local\DataResponse\Concerns;

use Illuminate\Http\JsonResponse;
use Local\DataResponse\DataResponse;

/**
 * Drop into any controller for successResponse()/failedResponse() helpers
 * built on the shared DataResponse envelope.
 */
trait BuildsDataResponses
{
    protected function successResponse(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $headers = []
    ): JsonResponse {
        return DataResponse::success($data, $message, $status, $headers);
    }

    protected function failedResponse(
        ?string $message = null,
        int $status = 400,
        mixed $errors = null
    ): JsonResponse {
        return DataResponse::error($message, $status, $errors);
    }
}
