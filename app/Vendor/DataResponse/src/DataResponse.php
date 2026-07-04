<?php

namespace Local\DataResponse;

use Illuminate\Http\JsonResponse;

/**
 * Builds the project's standard JSON envelope:
 *   { "success": bool, "message": string, "data": mixed }
 *   { "success": false, "message": string, "errors": mixed }
 *
 * Key names and default messages come from config/data_response.php so a
 * project can rename them without touching a single controller.
 */
class DataResponse
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $headers = []
    ): JsonResponse {
        return response()->json(
            static::successPayload($data, $message, $status),
            $status,
            $headers
        );
    }

    public static function error(
        ?string $message = null,
        int $status = 400,
        mixed $errors = null,
        array $headers = []
    ): JsonResponse {
        return response()->json(
            static::errorPayload($message, $errors),
            $status,
            $headers
        );
    }

    /**
     * Build a JSON response with a caller-supplied payload shape — for
     * endpoints that intentionally don't use the success/message/data
     * envelope (e.g. a health check's flat status/checks structure).
     * Keeps every JSON response in the app funneled through one place
     * even when the standard envelope doesn't apply.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function raw(array $payload, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json($payload, $status, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    public static function successPayload(mixed $data, ?string $message, int $status): array
    {
        return [
            static::key('success') => $status >= 200 && $status < 300,
            static::key('message') => $message ?? static::defaultMessage('success'),
            static::key('data') => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function errorPayload(?string $message, mixed $errors = null): array
    {
        $payload = [
            static::key('success') => false,
            static::key('message') => $message ?? static::defaultMessage('error'),
        ];

        if ($errors !== null) {
            $payload[static::key('errors')] = $errors;
        }

        return $payload;
    }

    protected static function key(string $name): string
    {
        return config("data_response.keys.{$name}", $name);
    }

    protected static function defaultMessage(string $type): string
    {
        return config("data_response.messages.{$type}", ucfirst($type));
    }
}
