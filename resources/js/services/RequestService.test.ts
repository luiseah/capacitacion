import { describe, expect, it, vi } from 'vitest';
import HttpClient from './http/HttpClient';
import RequestService from './RequestService';
import RequestTypeService from './RequestTypeService';

function fakeClient(response: unknown) {
    const request = vi.fn().mockResolvedValue(response);
    const client = {
        request,
        buildUrl: new HttpClient().buildUrl.bind(new HttpClient()),
    } as unknown as HttpClient;

    return { client, request };
}

const payload = { title: 'Laptops', priority: 'normal', metadata: { quantity: 1 } };

describe('RequestService', () => {
    it('all() unwraps the paginated envelope into items and meta', async () => {
        const { client, request } = fakeClient({
            status: 'success',
            message: 'Successful',
            data: [{ uuid: 'a' }],
            current_page: 2,
            last_page: 3,
            total: 31,
        });

        const page = await new RequestService(client).all({ page: 2 });

        expect(page.items).toEqual([{ uuid: 'a' }]);
        expect(page.meta).toMatchObject({ current_page: 2, last_page: 3, total: 31 });
        expect(page.meta).not.toHaveProperty('status');
        expect(request).toHaveBeenCalledWith(
            expect.objectContaining({ method: 'GET', url: '/api/v1/requests', params: { page: 2 } }),
        );
    });

    it('show() builds the URL and returns the unwrapped detail', async () => {
        const { client, request } = fakeClient({ status: 'success', data: { uuid: 'abc' } });

        await expect(new RequestService(client).show('abc')).resolves.toEqual({ uuid: 'abc' });
        expect(request).toHaveBeenCalledWith(
            expect.objectContaining({ method: 'GET', url: '/api/v1/requests/abc' }),
        );
    });

    it('create() posts the payload to the type URL', async () => {
        const { client, request } = fakeClient({ status: 'success', data: { uuid: 'new' } });

        await expect(new RequestService(client).create('purchase', payload)).resolves.toEqual({ uuid: 'new' });
        expect(request).toHaveBeenCalledWith({ method: 'POST', url: '/api/v1/requests/purchase', data: payload });
    });

    it('update() puts the payload and destroy() deletes', async () => {
        const { client, request } = fakeClient({ status: 'success', data: { uuid: 'abc' } });
        const service = new RequestService(client);

        await service.update('abc', payload);
        await expect(service.destroy('abc')).resolves.toBeUndefined();

        expect(request).toHaveBeenNthCalledWith(1, { method: 'PUT', url: '/api/v1/requests/abc', data: payload });
        expect(request).toHaveBeenNthCalledWith(2, { method: 'DELETE', url: '/api/v1/requests/abc' });
    });
});

describe('RequestTypeService', () => {
    it('all() and show() return the unwrapped data', async () => {
        const { client, request } = fakeClient({ status: 'success', data: [{ key: 'purchase' }] });
        const service = new RequestTypeService(client);

        await expect(service.all()).resolves.toEqual([{ key: 'purchase' }]);
        await service.show('work_permit');

        expect(request).toHaveBeenLastCalledWith(
            expect.objectContaining({ url: '/api/v1/request-types/work_permit' }),
        );
    });
});
