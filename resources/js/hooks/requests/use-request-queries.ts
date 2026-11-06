import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { requestKeys, requestTypeKeys } from '@/hooks/requests/query-keys';
import services from '@/services';
import type { RequestIndexParams } from '@/types';

export function useRequests(params: RequestIndexParams = {}) {
    return useQuery({
        queryKey: requestKeys.list(params),
        queryFn: ({ signal }) => services.requests.all(params, { signal }),
        placeholderData: keepPreviousData,
    });
}

export function useRequest(uuid: string | undefined) {
    return useQuery({
        queryKey: requestKeys.detail(uuid ?? ''),
        queryFn: ({ signal }) => services.requests.show(uuid as string, { signal }),
        enabled: Boolean(uuid),
    });
}

export function useRequestTypes() {
    return useQuery({
        queryKey: requestTypeKeys.list(),
        queryFn: ({ signal }) => services.requestTypes.all({ signal }),
        // Request types change rarely: keep them fresh for the whole session.
        staleTime: Infinity,
    });
}

export function useRequestType(key: string | undefined) {
    return useQuery({
        queryKey: requestTypeKeys.detail(key ?? ''),
        queryFn: ({ signal }) =>
            services.requestTypes.show(key as string, { signal }),
        enabled: Boolean(key),
        staleTime: Infinity,
    });
}
