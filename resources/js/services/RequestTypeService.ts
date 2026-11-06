import AbstractService from './AbstractService';
import type ResponseService from './ResponseService';
import type { ApiRequestTypeDefinition, RequestTypesExtras } from '@/types';

type ReadOptions = { signal?: AbortSignal };

export default class RequestTypeService extends AbstractService {
    /**
     * Get every active request type with its fields, plus the allowed priorities
     * (in `extras.priorities`)
     */
    public async all(
        options: ReadOptions = {},
    ): Promise<ResponseService<ApiRequestTypeDefinition[], RequestTypesExtras>> {
        return this.client.request({
            method: 'GET',
            url: '/api/v1/request-types',
            signal: options.signal,
            extras: ['priorities'],
        });
    }

    /**
     * Get one request type with its fields
     */
    public async show(
        key: string,
        options: ReadOptions = {},
    ): Promise<ResponseService<ApiRequestTypeDefinition>> {
        const url = this.client.buildUrl({
            url: '/api/v1/request-types/:key',
            params: { key },
        });

        return this.client.request({ method: 'GET', url, signal: options.signal });
    }
}
