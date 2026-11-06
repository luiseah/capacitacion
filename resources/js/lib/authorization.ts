import type { Permission, Role } from '@/generated/permissions';

/** What the backend shares about the current user (see `auth` Inertia props). */
export type Access = {
    roles: readonly string[];
    permissions: readonly string[];
};

export function hasRole(access: Access, role: Role): boolean {
    return access.roles.includes(role);
}

export function hasAnyRole(access: Access, roles: readonly Role[]): boolean {
    return roles.some((role) => hasRole(access, role));
}

export function hasPermission(access: Access, permission: Permission): boolean {
    return access.permissions.includes(permission);
}

/** Alias of `hasPermission`, mirroring Laravel's `can()`. */
export const can = hasPermission;

export function hasAnyPermission(
    access: Access,
    permissions: readonly Permission[],
): boolean {
    return permissions.some((permission) => hasPermission(access, permission));
}
