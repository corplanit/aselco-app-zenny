<?php

namespace App\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Canonical JSON error shape for /api/* :
 * { "message": string, "code": string, "errors"?: object }
 *
 * Success payloads are unchanged (resource objects / Laravel paginator).
 */
class ApiErrorResponse
{
    public static function fromException(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return self::json($e->getMessage() ?: 'The given data was invalid.', 422, 'VALIDATION_ERROR', [
                'errors' => $e->errors(),
            ]);
        }

        if ($e instanceof AuthenticationException) {
            return self::json('Unauthenticated.', 401, 'UNAUTHENTICATED');
        }

        if ($e instanceof AuthorizationException) {
            return self::json($e->getMessage() ?: 'This action is unauthorized.', 403, 'FORBIDDEN');
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return self::json('Too many requests. Please try again later.', 429, 'RATE_LIMITED');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $code = match ($status) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                409 => 'CONFLICT',
                422 => 'UNPROCESSABLE',
                429 => 'RATE_LIMITED',
                default => 'HTTP_'.$status,
            };

            return self::json($e->getMessage() !== '' ? $e->getMessage() : 'Request failed.', $status, $code);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function json(string $message, int $status, string $code, array $extra = []): JsonResponse
    {
        $payload = [
            'message' => $message,
            'code' => $code,
        ];

        if (isset($extra['errors'])) {
            $payload['errors'] = $extra['errors'];
        }

        return response()->json($payload, $status);
    }
}
