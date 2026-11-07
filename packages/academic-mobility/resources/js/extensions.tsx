import type { ComponentType } from 'react';
import type { Institution, MobilityDetail } from '@academic/types';

/**
 * Places of the mobility pages that other packages can fill (the agreements package does). The pages
 * never import those packages: they render `<MobilitySlot name="…" />` and whoever is installed registers a
 * component for it, once, when the host loads it (`import '@agreements/register'`). An empty slot renders nothing.
 */
export type MobilitySlots = {
    /** Below the institution picker of the creation form: choose something that binds that institution. */
    'create.partner': {
        institution: Institution;
        local: Institution;
        /** The id the package keeps for the mobility (`agreement_id`). */
        value: string | null;
        onChange: (id: string | null) => void;
        error?: string;
    };
    /** A row of the summary of a mobility. */
    'show.summary': { mobility: MobilityDetail; partnerId: string | null };
    /** A section below the summary of a mobility. */
    'show.section': { mobility: MobilityDetail; partner: Institution | null | undefined; partnerId: string | null };
};

type Registry = { [K in keyof MobilitySlots]?: ComponentType<MobilitySlots[K]> };

const registry: Registry = {};

export function registerMobilitySlot<K extends keyof MobilitySlots>(name: K, component: ComponentType<MobilitySlots[K]>): void {
    (registry as Record<K, ComponentType<MobilitySlots[K]>>)[name] = component;
}

export function MobilitySlot<K extends keyof MobilitySlots>({ name, ...props }: { name: K } & MobilitySlots[K]) {
    const Component = registry[name] as ComponentType<MobilitySlots[K]> | undefined;

    return Component ? <Component {...(props as unknown as MobilitySlots[K])} /> : null;
}
