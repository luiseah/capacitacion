import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { requestKeys, requestTypeKeys } from '@/hooks/requests/query-keys';
import {
    useCreateRequest,
    useDeleteRequest,
    useUpdateRequest,
} from '@/hooks/requests/use-request-mutations';
import {
    useRequest,
    useRequestType,
    useRequestTypes,
    useRequests,
} from '@/hooks/requests/use-request-queries';
import services from '@/services';
import ResponseService from '@/services/ResponseService';

vi.mock('@/services', () => ({
    default: {
        requests: {
            all: vi.fn(),
            show: vi.fn(),
            create: vi.fn(),
            update: vi.fn(),
            destroy: vi.fn(),
        },
        requestTypes: { all: vi.fn(), show: vi.fn() },
    },
    ApiError: class ApiError extends Error {},
}));

const requestService = services.requests;
const requestTypeService = services.requestTypes;

const payload = { title: 'Laptops', priority: 'normal', metadata: {} };
const detail = { uuid: 'abc', title: 'Laptops' };

const wrap = (data: unknown, message = 'Successful', extras: object = {}) =>
    ResponseService.from({ status: 'success', message, data, ...extras }) as never;

let queryClient: QueryClient;

function wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

beforeEach(() => {
    vi.clearAllMocks();
    queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
    });
});

describe('query keys', () => {
    it('nest list and detail keys under the feature key', () => {
        expect(requestKeys.list({ page: 2 })).toEqual(['requests', 'list', { page: 2 }]);
        expect(requestKeys.detail('abc')).toEqual(['requests', 'detail', 'abc']);
        expect(requestTypeKeys.detail('purchase')).toEqual(['request-types', 'detail', 'purchase']);
    });
});

describe('queries', () => {
    it('useRequests loads a page through the service', async () => {
        vi.mocked(requestService.all).mockResolvedValue(
            ResponseService.from(
                { status: 'success', data: [detail], current_page: 1, last_page: 1, per_page: 15, total: 1 },
                { extras: ['current_page'] },
            ) as never,
        );

        const { result } = renderHook(() => useRequests({ page: 2 }), { wrapper });

        await waitFor(() => expect(result.current.isSuccess).toBe(true));
        expect(result.current.data?.items).toHaveLength(1);
        expect(requestService.all).toHaveBeenCalledWith({ page: 2 }, expect.objectContaining({ signal: expect.anything() }));
    });

    it('useRequest is disabled without a uuid and loads with one', async () => {
        vi.mocked(requestService.show).mockResolvedValue(wrap(detail));

        const disabled = renderHook(() => useRequest(undefined), { wrapper });
        expect(disabled.result.current.fetchStatus).toBe('idle');
        expect(requestService.show).not.toHaveBeenCalled();

        const { result } = renderHook(() => useRequest('abc'), { wrapper });
        await waitFor(() => expect(result.current.data).toEqual(detail));
    });

    it('useRequestTypes and useRequestType load request types', async () => {
        vi.mocked(requestTypeService.all).mockResolvedValue(wrap([{ key: 'purchase' }], 'Successful', { priorities: ['low'] }));
        vi.mocked(requestTypeService.show).mockResolvedValue(wrap({ key: 'purchase' }));

        const list = renderHook(() => useRequestTypes(), { wrapper });
        const one = renderHook(() => useRequestType('purchase'), { wrapper });

        await waitFor(() => expect(list.result.current.data).toEqual({ types: [{ key: 'purchase' }], priorities: ['low'] }));
        await waitFor(() => expect(one.result.current.data).toEqual({ key: 'purchase' }));
    });

    it('exposes service failures as query errors', async () => {
        vi.mocked(requestService.show).mockRejectedValue(new Error('boom'));

        const { result } = renderHook(() => useRequest('abc'), { wrapper });

        await waitFor(() => expect(result.current.isError).toBe(true));
        expect(result.current.error?.message).toBe('boom');
    });
});

describe('mutations', () => {
    it('useCreateRequest caches the detail and invalidates the lists', async () => {
        vi.mocked(requestService.create).mockResolvedValue(wrap(detail, 'Request created'));
        const invalidate = vi.spyOn(queryClient, 'invalidateQueries');

        const { result } = renderHook(() => useCreateRequest(), { wrapper });
        await act(() => result.current.mutateAsync({ type: 'purchase', payload }));

        expect(requestService.create).toHaveBeenCalledWith('purchase', payload);
        expect(queryClient.getQueryData(requestKeys.detail('abc'))).toEqual(detail);
        expect(invalidate).toHaveBeenCalledWith({ queryKey: requestKeys.lists() });
    });

    it('useUpdateRequest refreshes the cached detail and the lists', async () => {
        vi.mocked(requestService.update).mockResolvedValue(wrap({ ...detail, title: 'Updated' }, 'Request updated'));
        const invalidate = vi.spyOn(queryClient, 'invalidateQueries');

        const { result } = renderHook(() => useUpdateRequest(), { wrapper });
        await act(() => result.current.mutateAsync({ uuid: 'abc', payload }));

        expect(queryClient.getQueryData<typeof detail>(requestKeys.detail('abc'))?.title).toBe('Updated');
        expect(invalidate).toHaveBeenCalledWith({ queryKey: requestKeys.lists() });
    });

    it('useDeleteRequest drops the cached detail and invalidates the lists', async () => {
        vi.mocked(requestService.destroy).mockResolvedValue(wrap(undefined, 'Request deleted'));
        queryClient.setQueryData(requestKeys.detail('abc'), detail);
        const invalidate = vi.spyOn(queryClient, 'invalidateQueries');

        const { result } = renderHook(() => useDeleteRequest(), { wrapper });
        const response = await act(() => result.current.mutateAsync('abc'));

        // The mutation resolves with the response, so the UI can show the server message.
        expect(response.message).toBe('Request deleted');

        expect(queryClient.getQueryData(requestKeys.detail('abc'))).toBeUndefined();
        expect(invalidate).toHaveBeenCalledWith({ queryKey: requestKeys.lists() });
    });
});
