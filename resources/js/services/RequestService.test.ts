import { describe, expect, it, vi } from 'vitest';
import HttpClient from './http/HttpClient';
import RequestService from './RequestService';
import ResponseService from './ResponseService';
import RequestTypeService from './RequestTypeService';

/** A client whose endpoints resolve with the given envelope, like the real one. */
function fakeClient(body: unknown, extras: readonly string[] = []) {
    const response = ResponseService.from(body, { extras: extras as never });
    const request = vi.fn().mockResolvedValue(response);
    const paginated = vi.fn().mockResolvedValue(response);
    const client = {
        request,
        paginated,
        buildUrl: new HttpClient().buildUrl.bind(new HttpClient()),
    } as unknown as HttpClient;

    return { client, request, paginated };
}

const payload = { title: 'Laptops', priority: 'normal', metadata: { quantity: 1 } };

describe('RequestService', () => {
    it('all() returns the response with its pagination as items and meta', async () => {
        const { client, paginated } = fakeClient({
            status: 'success',
            message: 'Successful',
            data: [{ uuid: 'a' }],
            current_page: 2,
            last_page: 3,
            per_page: 15,
            total: 31,
        });

        const response = await new RequestService(client).all({ page: 2 });
        const page = response.toPage();

        expect(response.message).toBe('Successful');
        expect(page.items).toEqual([{ uuid: 'a' }]);
        expect(page.meta).toMatchObject({ current_page: 2, last_page: 3, total: 31 });
        expect(page.meta).not.toHaveProperty('status');
        expect(paginated).toHaveBeenCalledWith(
            expect.objectContaining({ method: 'GET', url: '/api/v1/requests', params: { page: 2 } }),
        );
    });

    it('show() builds the URL and returns the detail with the message', async () => {
        const { client, request } = fakeClient({ status: 'success', message: 'Successful', data: { uuid: 'abc' } });

        const response = await new RequestService(client).show('abc');

        expect(response.data).toEqual({ uuid: 'abc' });
        expect(response.message).toBe('Successful');
        expect(request).toHaveBeenCalledWith(
            expect.objectContaining({ method: 'GET', url: '/api/v1/requests/abc' }),
        );
    });

    it('create() posts the payload to the type URL and exposes the server message', async () => {
        const { client, request } = fakeClient({ status: 'success', message: 'Request created', data: { uuid: 'new' } });

        const response = await new RequestService(client).create('purchase', payload);

        expect(response.data).toEqual({ uuid: 'new' });
        expect(response.message).toBe('Request created');
        expect(request).toHaveBeenCalledWith({ method: 'POST', url: '/api/v1/requests/purchase', data: payload });
    });

    it('update() puts the payload', async () => {
        const { client, request } = fakeClient({ status: 'success', message: 'Request updated', data: { uuid: 'abc' } });

        const response = await new RequestService(client).update('abc', payload);

        expect(response.message).toBe('Request updated');
        expect(request).toHaveBeenCalledWith({ method: 'PUT', url: '/api/v1/requests/abc', data: payload });
    });

    it('destroy() returns the response so the delete message can be shown', async () => {
        const { client, request } = fakeClient({ status: 'success', message: 'Request deleted' });

        const response = await new RequestService(client).destroy('abc');

        expect(response.message).toBe('Request deleted');
        expect(request).toHaveBeenCalledWith({ method: 'DELETE', url: '/api/v1/requests/abc' });
    });
});

describe('RequestTypeService', () => {
    it('all() asks for the priorities extra and returns the types', async () => {
        const { client, request } = fakeClient(
            { status: 'success', data: [{ key: 'purchase' }], priorities: ['low', 'normal'] },
            ['priorities'],
        );

        const response = await new RequestTypeService(client).all();

        expect(response.data).toEqual([{ key: 'purchase' }]);
        expect(response.extras.priorities).toEqual(['low', 'normal']);
        expect(request).toHaveBeenCalledWith(
            expect.objectContaining({ url: '/api/v1/request-types', extras: ['priorities'] }),
        );
    });

    it('show() builds the URL', async () => {
        const { client, request } = fakeClient({ status: 'success', data: { key: 'work_permit' } });

        await new RequestTypeService(client).show('work_permit');

        expect(request).toHaveBeenLastCalledWith(
            expect.objectContaining({ url: '/api/v1/request-types/work_permit' }),
        );
    });
});
