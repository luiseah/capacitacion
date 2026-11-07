import type { PaginationMeta } from '@/types';

export type MobilityStatus =
    | 'draft'
    | 'submitted'
    | 'under_review'
    | 'approved'
    | 'rejected'
    | 'cancelled'
    | 'completed';

export const MOBILITY_STATUSES: readonly MobilityStatus[] = [
    'draft',
    'submitted',
    'under_review',
    'approved',
    'rejected',
    'cancelled',
    'completed',
];

export type ParticipantKind = 'student' | 'professor' | 'staff';

export const PARTICIPANT_LABELS: Record<ParticipantKind, string> = {
    student: 'Student',
    professor: 'Professor',
    staff: 'Administrative staff',
};

export type ParticipantDetail = { label: string; value: string };

/** A person as a participant of one kind, with what identifies that profile (code, program...). */
export type ParticipantOption = {
    type: ParticipantKind;
    id: string;
    name: string;
    details: ParticipantDetail[];
};

export type MobilityDirection = 'incoming' | 'outgoing';
export type MobilityScope = 'national' | 'international';

export type Institution = {
    id: string;
    name: string;
    country: string;
    is_local: boolean;
    created_at: string | null;
};

export type InstitutionPayload = {
    name: string;
    country: string;
    is_local?: boolean;
};

/** What a mobility had to bring when it was created (a copy of the catalog entry). */
export type MobilityRequirement = {
    id: string;
    name: string;
    description: string | null;
    is_mandatory: boolean;
    position: number;
    fulfilled: boolean;
};

/** An entry of the catalog: it applies to the mobilities that match the criteria it names (empty = any). */
export type Requirement = {
    id: string;
    name: string;
    description: string | null;
    is_mandatory: boolean;
    position: number;
    direction: MobilityDirection | null;
    scope: MobilityScope | null;
    participant_kind: ParticipantKind | null;
    is_active: boolean;
    /** The texts as written (in English), whatever the language of the request. */
    source: { name: string; description: string | null };
    translations: Record<string, { name?: string; description?: string }>;
};

export type RequirementPayload = {
    name: string;
    description: string | null;
    direction: MobilityDirection | null;
    scope: MobilityScope | null;
    participant_kind: ParticipantKind | null;
    is_mandatory: boolean;
    is_active: boolean;
    position: number | null;
    translations: Record<string, { name?: string; description?: string }>;
};

export type RequirementIndexParams = {
    page?: number;
    search?: string;
    direction?: MobilityDirection;
    scope?: MobilityScope;
    participant_kind?: ParticipantKind;
    active?: boolean;
};

export type Mobility = {
    id: string;
    participant_type: ParticipantKind;
    participant_id: string;
    strategy: string;
    direction: MobilityDirection | null;
    scope: MobilityScope | null;
    origin?: Institution;
    destination?: Institution;
    /** The id of the agreement it is made under, if one was chosen (see the agreements package). */
    agreement_id?: string | null;
    requirements?: MobilityRequirement[];
    status: MobilityStatus;
    is_final: boolean;
    created_at: string | null;
    updated_at: string | null;
};

/** A mobility with who takes part in it, as shown in its own page. */
export type MobilityDetail = Mobility & { participant: ParticipantOption | null };

export type MobilitySortColumn =
    | 'created_at'
    | 'status'
    | 'participant_type'
    | 'strategy'
    | 'direction'
    | 'scope';

export type MobilityIndexParams = {
    page?: number;
    status?: MobilityStatus;
    participant_type?: ParticipantKind;
    direction?: MobilityDirection;
    scope?: MobilityScope;
    /** Relations to include: the ones the list shows. */
    with?: ('originInstitution' | 'destinationInstitution' | 'requirements')[];
    sort?: Partial<Record<MobilitySortColumn, 'asc' | 'desc'>>;
};

export type { PaginationMeta };

/** Body of the creation form. */
export type MobilityPayload = {
    participant_type: ParticipantKind;
    participant_id: string;
    strategy: string;
    /** Only for strategies that serve both directions. */
    direction?: MobilityDirection | null;
    scope: MobilityScope;
    /** The institution at the other end: the local one is placed by the backend. */
    institution_id: string;
    /** The agreement it is made under (optional): one in force with that institution. */
    agreement_id?: string | null;
    metadata?: Record<string, string>;
};

/** A kind of mobility; `direction` is set when the strategy fixes it. */
export type MobilityStrategyOption = {
    alias: string;
    direction: MobilityDirection | null;
};

/** One input of the form that registers a person that does not exist yet (a visitor). */
export type ParticipantField = {
    name: string;
    label: string;
    type?: string;
    required?: boolean;
};

/** The form that registers a person; `can_sample` offers the "fill with random data" button. */
export type ParticipantForm = {
    fields: ParticipantField[];
    can_sample: boolean;
};

/** Aliases the host registered; they feed the creation form. */
export type MobilityOptions = {
    /** Who can be chosen for each direction: own people leave, visitors arrive. */
    participant_types: Record<MobilityDirection, ParticipantKind[]>;
    /** Registration forms of the people that can be created from the mobility form, per direction and kind. */
    participant_forms: Partial<Record<MobilityDirection, Partial<Record<ParticipantKind, ParticipantForm>>>>;
    /** The signed-in user once per profile they have, when the host maps them. */
    current_participants: ParticipantOption[];
    strategies: MobilityStrategyOption[];
    default_strategy: string;
    institutions: Institution[];
};

export type InstitutionIndexParams = {
    page?: number;
    search?: string;
};

/** English text of an enum value (`under_review` => `Under review`), the key to translate. */
export const enumLabel = (value: string): string => {
    const text = value.replaceAll('_', ' ');

    return text.charAt(0).toUpperCase() + text.slice(1);
};
