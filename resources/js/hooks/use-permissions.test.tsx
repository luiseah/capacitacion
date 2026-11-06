import { renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Requests, Role } from '@/generated/permissions';
import { usePermissions } from '@/hooks/use-permissions';

const usePage = vi.fn();

vi.mock('@inertiajs/react', () => ({ usePage: () => usePage() }));

beforeEach(() => usePage.mockReset());

describe('usePermissions', () => {
    it('binds the helpers to the shared auth props', () => {
        usePage.mockReturnValue({
            props: { auth: { roles: [Role.Requester], permissions: [Requests.create] } },
        });

        const { result } = renderHook(() => usePermissions());

        expect(result.current.hasRole(Role.Requester)).toBe(true);
        expect(result.current.hasAnyRole([Role.Admin])).toBe(false);
        expect(result.current.can(Requests.create)).toBe(true);
        expect(result.current.hasPermission(Requests.update)).toBe(false);
        expect(result.current.hasAnyPermission([Requests.update, Requests.create])).toBe(true);
    });

    it('denies everything when there is no authenticated user', () => {
        usePage.mockReturnValue({ props: {} });

        const { result } = renderHook(() => usePermissions());

        expect(result.current.hasRole(Role.Admin)).toBe(false);
        expect(result.current.can(Requests.create)).toBe(false);
    });
});
