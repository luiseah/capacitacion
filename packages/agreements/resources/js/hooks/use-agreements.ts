import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { agreementService } from '@agreements/services/AgreementService';
import type { AgreementIndexParams, AgreementPayload } from '@agreements/types';

export const agreementKeys = {
    all: ['agreements'] as const,
    list: (params: AgreementIndexParams) => [...agreementKeys.all, 'list', params] as const,
    detail: (id: string) => [...agreementKeys.all, 'detail', id] as const,
    ofInstitution: (institutionId: string) => [...agreementKeys.all, 'institution', institutionId] as const,
};

export function useAgreements(params: AgreementIndexParams = {}) {
    return useQuery({
        queryKey: agreementKeys.list(params),
        queryFn: async ({ signal }) => (await agreementService.all(params, { signal })).toPage(),
        placeholderData: keepPreviousData,
    });
}

export function useAgreement(id: string | undefined) {
    return useQuery({
        queryKey: agreementKeys.detail(id ?? ''),
        queryFn: async ({ signal }) => (await agreementService.find(id as string, { signal })).data,
        enabled: id !== undefined,
    });
}

/** The agreements of the institution chosen in the mobility form; none while no institution is chosen. */
export function useAgreementsOf(institutionId: string) {
    return useQuery({
        queryKey: agreementKeys.ofInstitution(institutionId),
        queryFn: async ({ signal }) => (await agreementService.of(institutionId, { signal })).data,
        enabled: institutionId !== '',
    });
}

export function useSaveAgreement(id?: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: AgreementPayload) =>
            id === undefined ? agreementService.store(payload) : agreementService.update(id, payload),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: agreementKeys.all }),
    });
}

export function useDeleteAgreement() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: string) => agreementService.destroy(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: agreementKeys.all }),
    });
}

export function useUploadAgreementDocument() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, file }: { id: string; file: File }) => agreementService.uploadDocument(id, file),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: agreementKeys.all }),
    });
}

export function useRemoveAgreementDocument() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: string) => agreementService.removeDocument(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: agreementKeys.all }),
    });
}
