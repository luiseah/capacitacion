import AbstractService from './AbstractService';
import type ResponseService from './ResponseService';
import type {
    ApiRequestDetail,
    ApiRequestSummary,
    PaginationMeta,
    RequestIndexParams,
    RequestPayload,
} from '@/types';

type ReadOptions = { signal?: AbortSignal };

export default class RequestService extends AbstractService {
    /**
     * Get a page of the requests visible to the current user
     */
    public async all(
        params: RequestIndexParams = {},
        options: ReadOptions = {},
    ): Promise<ResponseService<ApiRequestSummary[], PaginationMeta>> {
        return this.client.paginated<ApiRequestSummary>({
            method: 'GET',
            url: '/api/v1/requests',
            params,
            signal: options.signal,
        });
    }

    /**
     * Get a request with its details
     */
    public async show(
        uuid: string,
        options: ReadOptions = {},
    ): Promise<ResponseService<ApiRequestDetail>> {
        const url = this.client.buildUrl({
            url: '/api/v1/requests/:uuid',
            params: { uuid },
        });

        return this.client.request({ method: 'GET', url, signal: options.signal });
    }

    /**
     * Create a request of the given type
     */
    public async create(
        type: string,
        payload: RequestPayload,
    ): Promise<ResponseService<ApiRequestDetail>> {
        const url = this.client.buildUrl({
            url: '/api/v1/requests/:type',
            params: { type },
        });

        return this.client.request({ method: 'POST', url, data: payload });
    }

    /**
     * Update a request
     */
    public async update(
        uuid: string,
        payload: RequestPayload,
    ): Promise<ResponseService<ApiRequestDetail>> {
        const url = this.client.buildUrl({
            url: '/api/v1/requests/:uuid',
            params: { uuid },
        });

        return this.client.request({ method: 'PUT', url, data: payload });
    }

    /**
     * Delete a request
     */
    public async destroy(uuid: string): Promise<ResponseService> {
        const url = this.client.buildUrl({
            url: '/api/v1/requests/:uuid',
            params: { uuid },
        });

        return this.client.request({ method: 'DELETE', url });
    }
}
