import AbstractService from './AbstractService';
import type {
    ApiRequestDetail,
    ApiRequestSummary,
    ApiResponse,
    PageResult,
    PaginatedApiResponse,
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
    ): Promise<PageResult<ApiRequestSummary>> {
        const response = await this.client.request<
            PaginatedApiResponse<ApiRequestSummary>
        >({
            method: 'GET',
            url: '/api/v1/requests',
            params,
            signal: options.signal,
        });

        const {
            data,
            abilities,
            status: _status,
            message: _message,
            ...meta
        } = response;

        return { items: data, meta, abilities };
    }

    /**
     * Get a request with its details
     */
    public async show(
        uuid: string,
        options: ReadOptions = {},
    ): Promise<ApiRequestDetail> {
        const url = this.client.buildUrl({
            url: '/api/v1/requests/:uuid',
            params: { uuid },
        });

        const response = await this.client.request<
            ApiResponse<ApiRequestDetail>
        >({ method: 'GET', url, signal: options.signal });

        return response.data;
    }

    /**
     * Create a request of the given type
     */
    public async create(
        type: string,
        payload: RequestPayload,
    ): Promise<ApiRequestDetail> {
        const url = this.client.buildUrl({
            url: '/api/v1/requests/:type',
            params: { type },
        });

        const response = await this.client.request<
            ApiResponse<ApiRequestDetail>
        >({ method: 'POST', url, data: payload });

        return response.data;
    }

    /**
     * Update a request
     */
    public async update(
        uuid: string,
        payload: RequestPayload,
    ): Promise<ApiRequestDetail> {
        const url = this.client.buildUrl({
            url: '/api/v1/requests/:uuid',
            params: { uuid },
        });

        const response = await this.client.request<
            ApiResponse<ApiRequestDetail>
        >({ method: 'PUT', url, data: payload });

        return response.data;
    }

    /**
     * Delete a request
     */
    public async destroy(uuid: string): Promise<void> {
        const url = this.client.buildUrl({
            url: '/api/v1/requests/:uuid',
            params: { uuid },
        });

        await this.client.request({ method: 'DELETE', url });
    }
}
