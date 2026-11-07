import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { mobilityService } from '@academic/services/MobilityService';
import type { MobilityDirection, MobilityIndexParams, MobilityPayload, MobilityScope, ParticipantKind } from '@academic/types';

export const mobilityKeys = {
    all: ['academic-mobility', 'mobilities'] as const,
    participants: (type: string, direction: string, institutionId: string, search: string) =>
        [...mobilityKeys.all, 'participants', type, direction, institutionId, search] as const,
    institutions: (scope: string, search: string) => [...mobilityKeys.all, 'institutions', scope, search] as const,
    detail: (id: string) => [...mobilityKeys.all, 'detail', id] as const,
    options: () => [...mobilityKeys.all, 'options'] as const,
    list: (params: MobilityIndexParams) => [...mobilityKeys.all, 'list', params] as const,
};

export function useMobilities(params: MobilityIndexParams = {}) {
    return useQuery({
        queryKey: mobilityKeys.list(params),
        queryFn: async ({ signal }) => (await mobilityService.all(params, { signal })).toPage(),
        placeholderData: keepPreviousData,
    });
}

export function useMobilityOptions() {
    return useQuery({
        queryKey: mobilityKeys.options(),
        queryFn: async ({ signal }) => (await mobilityService.options({ signal })).data,
    });
}

export function useCreateMobility() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: MobilityPayload) => mobilityService.store(payload),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: mobilityKeys.all }),
    });
}

/** Who can be picked: own people for an outgoing mobility, visitors of the origin institution for an incoming one. */
export function useParticipants(
    type: ParticipantKind | '',
    direction: MobilityDirection | '',
    institutionId: string,
    search: string,
    enabled = true,
) {
    return useQuery({
        queryKey: mobilityKeys.participants(type, direction, institutionId, search),
        queryFn: async ({ signal }) =>
            (
                await mobilityService.participants(
                    { type: type as ParticipantKind, direction: direction as MobilityDirection, institutionId, search },
                    { signal },
                )
            ).data,
        enabled: enabled && type !== '' && direction !== '' && (direction === 'outgoing' || institutionId !== ''),
        placeholderData: keepPreviousData,
    });
}

/** Partner institutions of the scope matching what was typed (at most 20: the picker searches, it does not list). */
export function useInstitutionOptions(scope: MobilityScope | '', search: string, enabled = true, anyScope = false) {
    return useQuery({
        queryKey: mobilityKeys.institutions(scope, search),
        queryFn: async ({ signal }) =>
            (await mobilityService.institutionOptions({ scope, search }, { signal })).data,
        enabled: enabled && (anyScope || scope !== ''),
        placeholderData: keepPreviousData,
    });
}

export function useCreateParticipant() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: Parameters<typeof mobilityService.storeParticipant>[0]) => mobilityService.storeParticipant(payload),
        // The new person has to show up in the pickers.
        onSuccess: () => queryClient.invalidateQueries({ queryKey: [...mobilityKeys.all, 'participants'] }),
    });
}

export function useSampleParticipant() {
    return useMutation({
        mutationFn: (params: { type: ParticipantKind; institutionId: string }) => mobilityService.sampleParticipant(params),
    });
}

/** One mobility with the requirements it was created with and who takes part in it. */
export function useMobility(id: string | undefined) {
    return useQuery({
        queryKey: mobilityKeys.detail(id ?? ''),
        queryFn: async ({ signal }) => (await mobilityService.mobility(id as string, { signal })).data,
        enabled: id !== undefined,
    });
}
