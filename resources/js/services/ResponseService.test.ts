import { describe, expect, it } from 'vitest';
import { ApiError } from '@/services';
import type { PaginatedApiResponse, PaginationMeta, RequestTypesExtras } from '@/types';
import ResponseService, { paginationKeys } from './ResponseService';

const page: PaginatedApiResponse<{ uuid: string }> = {
    status: 'success',
    data: [{ uuid: 'a' }],
    current_page: 2,
    last_page: 3,
    per_page: 15,
    total: 31,
    from: 16,
    to: 30,
    first_page_url: '/p?page=1',
    last_page_url: '/p?page=3',
    next_page_url: '/p?page=3',
    prev_page_url: '/p?page=1',
    path: '/p',
};

describe('ResponseService', () => {
    it('exposes the typed data, message and extras of the envelope', () => {
        const response = ResponseService.from<{ key: string }[], RequestTypesExtras>(
            {
                status: 'success',
                message: 'Successful',
                data: [{ key: 'purchase' }],
                priorities: ['low', 'normal'],
            },
            { extras: ['priorities'] },
        );

        expect(response.data).toEqual([{ key: 'purchase' }]);
        expect(response.message).toBe('Successful');
        expect(response.extras.priorities).toEqual(['low', 'normal']);
        expect(response.extras).not.toHaveProperty('data');
    });

    it('fails when a required extra is missing from the response', () => {
        const body = { status: 'success', data: [] };

        expect(() =>
            ResponseService.from<never[], RequestTypesExtras>(body, { extras: ['priorities'] }),
        ).toThrow('The response is missing: priorities');
    });

    it('toPage() splits the pagination fields from the items', () => {
        const response = ResponseService.from<{ uuid: string }[], PaginationMeta>(page, {
            extras: paginationKeys,
        });

        expect(response.extras.current_page).toBe(2);

        const result = response.toPage();
        expect(result.items).toEqual([{ uuid: 'a' }]);
        expect(result.meta).toMatchObject({ current_page: 2, last_page: 3, total: 31 });
        expect(result.meta).not.toHaveProperty('data');
        expect(result.meta).not.toHaveProperty('status');
    });

    it('accepts a success response without data', () => {
        const response = ResponseService.from({ status: 'success', message: 'Request deleted' });

        expect(response.data).toBeUndefined();
        expect(response.message).toBe('Request deleted');
    });

    it('rejects bodies that are not a success envelope', () => {
        for (const body of [null, 'oops', {}, { status: 'error' }]) {
            expect(() => ResponseService.from(body)).toThrow(ApiError);
        }
    });
});
