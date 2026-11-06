import AbstractService from './AbstractService';
import type { ApiRequestTypeDefinition, ApiResponse } from '@/types';

type ReadOptions = { signal?: AbortSignal };

export default class RequestTypeService extends AbstractService {
    /**
     * Get every active request type with its fields
     */
    public async all(
        options: ReadOptions = {},
    ): Promise<ApiRequestTypeDefinition[]> {
        const response = await this.client.request<
            ApiResponse<ApiRequestTypeDefinition[]>
        >({
            method: 'GET',
            url: '/api/v1/request-types',
            signal: options.signal,
        });

        return response.data;
    }

    /**
     * Get one request type with its fields
     */
    public async show(
        key: string,
        options: ReadOptions = {},
    ): Promise<ApiRequestTypeDefinition> {
        const url = this.client.buildUrl({
            url: '/api/v1/request-types/:key',
            params: { key },
        });

        const response = await this.client.request<
            ApiResponse<ApiRequestTypeDefinition>
        >({ method: 'GET', url, signal: options.signal });

        return response.data;
    }
}
