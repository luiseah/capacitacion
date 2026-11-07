import AbstractService from './AbstractService';
import type ResponseService from './ResponseService';
import type { ApiUser, PaginationMeta, UserIndexParams, UserPayload } from '@/types';

type ReadOptions = { signal?: AbortSignal };

export default class UserService extends AbstractService {
    /**
     * Get a page of users, optionally filtered by search term and role
     */
    public async all(
        params: UserIndexParams = {},
        options: ReadOptions = {},
    ): Promise<ResponseService<ApiUser[], PaginationMeta>> {
        return this.client.paginated<ApiUser>({
            method: 'GET',
            url: '/api/v1/users',
            params,
            signal: options.signal,
        });
    }

    /**
     * Get a user with their roles and abilities
     */
    public async show(
        id: number,
        options: ReadOptions = {},
    ): Promise<ResponseService<ApiUser>> {
        const url = this.client.buildUrl({
            url: '/api/v1/users/:id',
            params: { id },
        });

        return this.client.request({ method: 'GET', url, signal: options.signal });
    }

    /**
     * Update a user's name, email and roles
     */
    public async update(
        id: number,
        payload: UserPayload,
    ): Promise<ResponseService<ApiUser>> {
        const url = this.client.buildUrl({
            url: '/api/v1/users/:id',
            params: { id },
        });

        return this.client.request({ method: 'PUT', url, data: payload });
    }

    /**
     * Delete a user
     */
    public async destroy(id: number): Promise<ResponseService> {
        const url = this.client.buildUrl({
            url: '/api/v1/users/:id',
            params: { id },
        });

        return this.client.request({ method: 'DELETE', url });
    }
}
