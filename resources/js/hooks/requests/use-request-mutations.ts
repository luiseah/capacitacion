import { useMutation, useQueryClient } from '@tanstack/react-query';
import { requestKeys } from '@/hooks/requests/query-keys';
import services from '@/services';
import type { RequestPayload } from '@/types';

export function useCreateRequest() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ type, payload }: { type: string; payload: RequestPayload }) =>
            services.requests.create(type, payload),
        onSuccess: (response) => {
            queryClient.setQueryData(requestKeys.detail(response.data.uuid), response.data);

            return queryClient.invalidateQueries({ queryKey: requestKeys.lists() });
        },
    });
}

export function useUpdateRequest() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ uuid, payload }: { uuid: string; payload: RequestPayload }) =>
            services.requests.update(uuid, payload),
        onSuccess: (response) => {
            queryClient.setQueryData(requestKeys.detail(response.data.uuid), response.data);

            return queryClient.invalidateQueries({ queryKey: requestKeys.lists() });
        },
    });
}

export function useDeleteRequest() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (uuid: string) => services.requests.destroy(uuid),
        onSuccess: (_result, uuid) => {
            queryClient.removeQueries({ queryKey: requestKeys.detail(uuid) });

            return queryClient.invalidateQueries({ queryKey: requestKeys.lists() });
        },
    });
}
