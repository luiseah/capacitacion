import axios, { AxiosError } from 'axios';
import type { AxiosAdapter, InternalAxiosRequestConfig } from 'axios';
import { describe, expect, it, vi } from 'vitest';
import ApiError from './ApiError';
import HttpClient from './HttpClient';

type Reply = { status?: number; data?: unknown };

function clientWith(reply: Reply | Error, options: { getToken?: () => string | null } = {}) {
    const adapter = vi.fn<AxiosAdapter>(async (config: InternalAxiosRequestConfig) => {
        if (reply instanceof Error) {
            throw reply;
        }

        const response = {
            data: reply.data ?? null,
            status: reply.status ?? 200,
            statusText: '',
            headers: {},
            config,
        };

        if (response.status >= 400) {
            throw new AxiosError('failed', 'ERR_BAD_REQUEST', config, null, response);
        }

        return response;
    });

    const client = new HttpClient({
        instance: axios.create({ adapter }),
        getToken: options.getToken,
    });

    return { client, adapter };
}

describe('HttpClient.buildUrl', () => {
    const { client } = clientWith({ data: { status: 'success' } });

    it('replaces path parameters and encodes them', () => {
        expect(
            client.buildUrl({ url: '/api/v1/requests/:uuid', params: { uuid: 'a b' } }),
        ).toBe('/api/v1/requests/a%20b');
    });

    it('appends the query string, skipping empty values and expanding arrays', () => {
        expect(
            client.buildUrl({
                url: '/api/v1/requests',
                query: { page: 2, tags: ['x', 'y'], skip: null, none: undefined },
            }),
        ).toBe('/api/v1/requests?page=2&tags%5B%5D=x&tags%5B%5D=y');
    });

    it('throws when a path parameter is missing', () => {
        expect(() => client.buildUrl({ url: '/api/v1/requests/:uuid' })).toThrow(
            'Missing URL parameter "uuid"',
        );
    });
});

describe('HttpClient.request', () => {
    it('returns the response body and sends JSON headers, query and body', async () => {
        const { client, adapter } = clientWith({
            data: { status: 'success', message: 'Request created', data: { uuid: 'a' } },
        });

        const response = await client.request<{ uuid: string }>({
            method: 'POST',
            url: '/api/v1/requests/purchase',
            params: { page: 1 },
            data: { title: 'Laptops' },
        });

        expect(response.data).toEqual({ uuid: 'a' });
        expect(response.message).toBe('Request created');

        const config = adapter.mock.calls[0][0];
        expect(config.method).toBe('post');
        expect(config.params).toEqual({ page: 1 });
        expect(JSON.parse(config.data)).toEqual({ title: 'Laptops' });
        expect(config.headers.Accept).toBe('application/json');
        expect(config.headers.get('X-Requested-With')).toBe('XMLHttpRequest');
    });

    it('adds the bearer token when one is available', async () => {
        const { client, adapter } = clientWith({ data: { status: 'success' } }, { getToken: () => 'secret' });

        await client.request({ url: '/api/v1/user' });

        expect(adapter.mock.calls[0][0].headers.get('Authorization')).toBe('Bearer secret');
    });

    it('normalizes validation failures into ApiError with field errors', async () => {
        const { client } = clientWith({
            status: 422,
            data: { message: 'Invalid', errors: { 'metadata.currency': ['The selected currency is invalid.'] } },
        });

        const error = await client.request({ url: '/x' }).catch((e: unknown) => e);

        expect(error).toBeInstanceOf(ApiError);
        expect(error).toMatchObject({ status: 422, message: 'Invalid', isValidation: true });
        expect((error as ApiError).firstError('metadata.currency')).toBe('The selected currency is invalid.');
    });

    it('normalizes timeouts and network failures', async () => {
        const timeout = new AxiosError('timeout', 'ECONNABORTED');
        const offline = new AxiosError('offline', 'ERR_NETWORK');

        await expect(clientWith(timeout).client.request({ url: '/x' })).rejects.toMatchObject({ status: 408 });
        await expect(clientWith(offline).client.request({ url: '/x' })).rejects.toMatchObject({ status: 0 });
    });

    it('lets cancellations propagate untouched', async () => {
        const canceled = new axios.CanceledError('canceled');

        await expect(clientWith(canceled).client.request({ url: '/x' })).rejects.toBe(canceled);
    });

    it('checks the endpoint extras declared in the config', async () => {
        const { client } = clientWith({ data: { status: 'success', data: [] } });

        await expect(
            client.request<never[], { priorities: string[] }>({ url: '/x', extras: ['priorities'] }),
        ).rejects.toThrow('The response is missing: priorities');
    });

    it('rejects a body that is not a success envelope', async () => {
        await expect(clientWith({ data: 'oops' }).client.request({ url: '/x' })).rejects.toBeInstanceOf(ApiError);
    });

    it('paginated() guarantees the pagination fields', async () => {
        const ok = clientWith({
            data: { status: 'success', data: [{ id: 1 }], current_page: 1, last_page: 1, per_page: 15, total: 1 },
        });

        const response = await ok.client.paginated<{ id: number }>({ url: '/x' });
        expect(response.toPage().items).toEqual([{ id: 1 }]);

        await expect(
            clientWith({ data: { status: 'success', data: [] } }).client.paginated({ url: '/x' }),
        ).rejects.toThrow('The response is missing: current_page');
    });
});
