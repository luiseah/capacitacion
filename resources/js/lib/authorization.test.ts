import { describe, expect, it } from 'vitest';
import { Requests, Role } from '@/generated/permissions';
import { can, hasAnyPermission, hasAnyRole, hasPermission, hasRole } from '@/lib/authorization';

const requester = { roles: [Role.Requester], permissions: [Requests.create] };
const nobody = { roles: [], permissions: [] };

describe('roles', () => {
    it('hasRole checks the assigned roles', () => {
        expect(hasRole(requester, Role.Requester)).toBe(true);
        expect(hasRole(requester, Role.Admin)).toBe(false);
        expect(hasRole(nobody, Role.Requester)).toBe(false);
    });

    it('hasAnyRole is true when at least one role matches', () => {
        expect(hasAnyRole(requester, [Role.Admin, Role.Requester])).toBe(true);
        expect(hasAnyRole(requester, [Role.Admin])).toBe(false);
        expect(hasAnyRole(requester, [])).toBe(false);
    });
});

describe('permissions', () => {
    it('hasPermission and can check the assigned permissions', () => {
        expect(hasPermission(requester, Requests.create)).toBe(true);
        expect(can(requester, Requests.update)).toBe(false);
        expect(can(nobody, Requests.create)).toBe(false);
    });

    it('hasAnyPermission is true when at least one permission matches', () => {
        expect(hasAnyPermission(requester, [Requests.update, Requests.create])).toBe(true);
        expect(hasAnyPermission(requester, [Requests.update, Requests.destroy])).toBe(false);
        expect(hasAnyPermission(requester, [])).toBe(false);
    });
});
