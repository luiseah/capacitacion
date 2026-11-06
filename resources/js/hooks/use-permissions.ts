import { usePage } from '@inertiajs/react';
import type { Permission, Role } from '@/generated/permissions';
import * as authorization from '@/lib/authorization';
import type { Access } from '@/lib/authorization';
import type { Auth } from '@/types';

/**
 * Authorization helpers bound to the current user's roles and permissions.
 * The backend stays authoritative: use this to show or hide UI, never to
 * protect data.
 *
 * @example
 * const { can, hasRole } = usePermissions();
 * if (can(Requests.create)) { ... }
 */
export function usePermissions() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const access: Access = {
        roles: auth?.roles ?? [],
        permissions: auth?.permissions ?? [],
    };

    return {
        hasRole: (role: Role) => authorization.hasRole(access, role),
        hasAnyRole: (roles: readonly Role[]) => authorization.hasAnyRole(access, roles),
        hasPermission: (permission: Permission) => authorization.hasPermission(access, permission),
        can: (permission: Permission) => authorization.can(access, permission),
        hasAnyPermission: (permissions: readonly Permission[]) =>
            authorization.hasAnyPermission(access, permissions),
    };
}
