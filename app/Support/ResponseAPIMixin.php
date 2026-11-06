<?php


namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ResponseAPIMixin
{
    /**
     * Builds a general API JSON response.
     *
     * @return \Closure(mixed $data, ?string $message = null, array $extra = [], int $status = 200): \Illuminate\Http\JsonResponse
     */
    public function api()
    {
        return function (mixed $data, ?string $message = null, array $extra = [], int $status = 200): JsonResponse {
            $attributes = $data instanceof LengthAwarePaginator
                ? [...$data->toArray()]
                : ['data' => $data];

            $response = ['status' => 'success'];

            if (!is_null($message)) {
                $response['message'] = $message;
            }

            $response = [
                ...$response,
                ...$attributes,
                ...$extra
            ];

            return response()->json($response, $status);
        };
    }

    /**
     * Builds a simple success JSON response.
     *
     * @return \Closure(string $message, array $extra = [], int $status = 200): \Illuminate\Http\JsonResponse
     */
    public function success()
    {
        return function (string $message, array $extra = [], int $status = 200): JsonResponse {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                ...$extra
            ], $status);
        };
    }


    /**
     * Builds an error JSON response.
     *
     * @return \Closure(string $message, int $status = 400, array $extra = []): \Illuminate\Http\JsonResponse
     */
    public function error()
    {
        return function (string $message, int $status = 400, array $extra = []): JsonResponse {
            return response()->json([
                'status' => 'error',
                'message' => $message,
                ...$extra
            ], $status);
        };
    }
}