import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { userKeys } from '@/hooks/users/query-keys';
import services from '@/services';
import type { UserIndexParams } from '@/types';

export function useUsers(params: UserIndexParams = {}) {
    return useQuery({
        queryKey: userKeys.list(params),
        queryFn: async ({ signal }) =>
            (await services.users.all(params, { signal })).toPage(),
        placeholderData: keepPreviousData,
    });
}

export function useUser(id: number | undefined) {
    return useQuery({
        queryKey: userKeys.detail(id ?? 0),
        queryFn: async ({ signal }) =>
            (await services.users.show(id as number, { signal })).data,
        enabled: id !== undefined,
    });
}
