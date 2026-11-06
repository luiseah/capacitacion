import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { requestKeys, requestTypeKeys } from '@/hooks/requests/query-keys';
import services from '@/services';
import type { RequestIndexParams, RequestTypeCatalog } from '@/types';

export function useRequests(params: RequestIndexParams = {}) {
    return useQuery({
        queryKey: requestKeys.list(params),
        queryFn: async ({ signal }) =>
            (await services.requests.all(params, { signal })).toPage(),
        placeholderData: keepPreviousData,
    });
}

export function useRequest(uuid: string | undefined) {
    return useQuery({
        queryKey: requestKeys.detail(uuid ?? ''),
        queryFn: async ({ signal }) =>
            (await services.requests.show(uuid as string, { signal })).data,
        enabled: Boolean(uuid),
    });
}

export function useRequestTypes() {
    return useQuery({
        queryKey: requestTypeKeys.list(),
        queryFn: async ({ signal }): Promise<RequestTypeCatalog> => {
            const response = await services.requestTypes.all({ signal });

            return { types: response.data, priorities: response.extras.priorities };
        },
        // Request types change rarely: keep them fresh for the whole session.
        staleTime: Infinity,
    });
}

export function useRequestType(key: string | undefined) {
    return useQuery({
        queryKey: requestTypeKeys.detail(key ?? ''),
        queryFn: async ({ signal }) =>
            (await services.requestTypes.show(key as string, { signal })).data,
        enabled: Boolean(key),
        staleTime: Infinity,
    });
}
