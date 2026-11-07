import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from '@/hooks/use-translation';
import { mobilityService } from '@academic/services/MobilityService';
import type {
    MobilityDirection,
    MobilityScope,
    ParticipantKind,
    RequirementIndexParams,
    RequirementPayload,
} from '@academic/types';

// The texts come from the server in the language of the request, so the language is part of every key.
export const requirementKeys = {
    all: ['academic-mobility', 'requirements'] as const,
    list: (locale: string, params: RequirementIndexParams) => [...requirementKeys.all, 'list', locale, params] as const,
    detail: (id: string) => [...requirementKeys.all, 'detail', id] as const,
    preview: (locale: string, direction: string, scope: string, type: string) =>
        [...requirementKeys.all, 'preview', locale, direction, scope, type] as const,
};

/** What a mobility of this direction, scope and participant kind will have to bring (the creation form). */
export function useRequirementsPreview(direction: MobilityDirection | '', scope: MobilityScope | '', type: ParticipantKind | '') {
    const { locale } = useTranslation();

    return useQuery({
        queryKey: requirementKeys.preview(locale, direction, scope, type),
        queryFn: async ({ signal }) =>
            (
                await mobilityService.requirementsFor(
                    { direction: direction as MobilityDirection, scope: scope as MobilityScope, type: type as ParticipantKind },
                    { signal },
                )
            ).data,
        enabled: direction !== '' && scope !== '' && type !== '',
        placeholderData: keepPreviousData,
    });
}

export function useRequirements(params: RequirementIndexParams = {}) {
    const { locale } = useTranslation();

    return useQuery({
        queryKey: requirementKeys.list(locale, params),
        queryFn: async ({ signal }) => (await mobilityService.requirements(params, { signal })).toPage(),
        placeholderData: keepPreviousData,
    });
}

export function useRequirement(id: string | undefined) {
    return useQuery({
        queryKey: requirementKeys.detail(id ?? ''),
        queryFn: async ({ signal }) => (await mobilityService.requirement(id as string, { signal })).data,
        enabled: id !== undefined,
    });
}

export function useSaveRequirement(id?: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: RequirementPayload) =>
            id === undefined ? mobilityService.storeRequirement(payload) : mobilityService.updateRequirement(id, payload),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: requirementKeys.all }),
    });
}

export function useDeleteRequirement() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: string) => mobilityService.destroyRequirement(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: requirementKeys.all }),
    });
}
