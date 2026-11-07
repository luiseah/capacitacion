import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { mobilityKeys } from '@academic/hooks/use-mobilities';
import { mobilityService } from '@academic/services/MobilityService';
import type { InstitutionIndexParams, InstitutionPayload } from '@academic/types';

export const institutionKeys = {
    all: ['academic-mobility', 'institutions'] as const,
    list: (params: InstitutionIndexParams) => [...institutionKeys.all, 'list', params] as const,
    detail: (id: string) => [...institutionKeys.all, 'detail', id] as const,
};

export function useInstitutions(params: InstitutionIndexParams = {}) {
    return useQuery({
        queryKey: institutionKeys.list(params),
        queryFn: async ({ signal }) => (await mobilityService.institutions(params, { signal })).toPage(),
        placeholderData: keepPreviousData,
    });
}

export function useInstitution(id: string | undefined) {
    return useQuery({
        queryKey: institutionKeys.detail(id ?? ''),
        queryFn: async ({ signal }) => (await mobilityService.institution(id as string, { signal })).data,
        enabled: id !== undefined,
    });
}

/** The mobility form lists institutions too, so every change refreshes both. */
function useRefresh() {
    const queryClient = useQueryClient();

    return () =>
        Promise.all([
            queryClient.invalidateQueries({ queryKey: institutionKeys.all }),
            queryClient.invalidateQueries({ queryKey: mobilityKeys.options() }),
        ]);
}

export function useSaveInstitution(id?: string) {
    const refresh = useRefresh();

    return useMutation({
        mutationFn: (payload: InstitutionPayload) =>
            id === undefined ? mobilityService.storeInstitution(payload) : mobilityService.updateInstitution(id, payload),
        onSuccess: refresh,
    });
}

export function useDeleteInstitution() {
    const refresh = useRefresh();

    return useMutation({
        mutationFn: (id: string) => mobilityService.destroyInstitution(id),
        onSuccess: refresh,
    });
}
