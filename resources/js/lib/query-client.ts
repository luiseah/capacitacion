import { QueryClient } from '@tanstack/react-query';
import { ApiError } from '@/services';

/**
 * Do not retry client errors (validation, authorization, not found): repeating
 * them cannot succeed. Network and server errors get two extra attempts.
 */
export function shouldRetry(failureCount: number, error: unknown): boolean {
    if (error instanceof ApiError && error.status >= 400 && error.status < 500) {
        return false;
    }

    return failureCount < 2;
}

export function createQueryClient(): QueryClient {
    return new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 30_000,
                refetchOnWindowFocus: false,
                retry: shouldRetry,
            },
            mutations: { retry: false },
        },
    });
}
