<?php

namespace Module\ApiResponse;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Macros of the shared JSON envelope, available as `Response::api()`,
 * `Response::success()` and `Response::error()`:
 *
 *   { "status": "success" | "error", "message"?: string, "data"?: mixed, ...extra }
 *
 * A length-aware paginator is spread next to `status` (`data`, `current_page`,
 * `last_page`, `per_page`, `total`, links...), the shape the front end's
 * `HttpClient::paginated()` expects.
 */
class ResponseMixin
{
    /**
     * General response; `$extra` fields go at the top level.
     *
     * @return Closure(mixed $data, ?string $message = null, array $extra = [], int $status = 200): JsonResponse
     */
    public function api(): Closure
    {
        return function (mixed $data, ?string $message = null, array $extra = [], int $status = 200): JsonResponse {
            $attributes = $data instanceof LengthAwarePaginator
                ? $data->toArray()
                : ['data' => $data];

            return response()->json([
                'status' => 'success',
                ...($message !== null ? ['message' => $message] : []),
                ...$attributes,
                ...$extra,
            ], $status);
        };
    }

    /**
     * Success with a message only.
     *
     * @return Closure(string $message, array $extra = [], int $status = 200): JsonResponse
     */
    public function success(): Closure
    {
        return fn (string $message, array $extra = [], int $status = 200): JsonResponse => response()->json(
            ['status' => 'success', 'message' => $message, ...$extra],
            $status,
        );
    }

    /**
     * @return Closure(string $message, int $status = 400, array $extra = []): JsonResponse
     */
    public function error(): Closure
    {
        return fn (string $message, int $status = 400, array $extra = []): JsonResponse => response()->json(
            ['status' => 'error', 'message' => $message, ...$extra],
            $status,
        );
    }
}
