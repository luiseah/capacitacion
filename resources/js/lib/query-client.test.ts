import { describe, expect, it } from 'vitest';
import { shouldRetry } from '@/lib/query-client';
import { ApiError } from '@/services';

describe('shouldRetry', () => {
    it('never retries client errors', () => {
        for (const status of [401, 403, 404, 422]) {
            expect(shouldRetry(0, new ApiError(status, 'x'))).toBe(false);
        }
    });

    it('retries network and server errors up to two times', () => {
        expect(shouldRetry(0, new ApiError(500, 'x'))).toBe(true);
        expect(shouldRetry(1, new ApiError(0, 'x'))).toBe(true);
        expect(shouldRetry(2, new ApiError(500, 'x'))).toBe(false);
    });
});
