import { useMutation, useQueryClient } from '@tanstack/react-query';
import { userKeys } from '@/hooks/users/query-keys';
import services from '@/services';
import type { UserPayload } from '@/types';

export function useUpdateUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, payload }: { id: number; payload: UserPayload }) =>
            services.users.update(id, payload),
        onSuccess: (response) => {
            queryClient.setQueryData(userKeys.detail(response.data.id), response.data);

            return queryClient.invalidateQueries({ queryKey: userKeys.lists() });
        },
    });
}

export function useDeleteUser() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => services.users.destroy(id),
        onSuccess: (_result, id) => {
            queryClient.removeQueries({ queryKey: userKeys.detail(id) });

            return queryClient.invalidateQueries({ queryKey: userKeys.lists() });
        },
    });
}
