import type { RequestIndexParams } from '@/types';

/**
 * Single source of truth for the cache keys of the requests feature. Keys are
 * hierarchical so invalidating `requestKeys.lists()` refreshes every page.
 */
export const requestKeys = {
    all: ['requests'] as const,
    lists: () => [...requestKeys.all, 'list'] as const,
    list: (params: RequestIndexParams) =>
        [...requestKeys.lists(), params] as const,
    details: () => [...requestKeys.all, 'detail'] as const,
    detail: (uuid: string) => [...requestKeys.details(), uuid] as const,
};

export const requestTypeKeys = {
    all: ['request-types'] as const,
    list: () => [...requestTypeKeys.all, 'list'] as const,
    detail: (key: string) => [...requestTypeKeys.all, 'detail', key] as const,
};
