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

export type AgreementType = 'framework' | 'specific';
export type AgreementParties = 'bilateral' | 'multilateral';
export type AgreementStatus = 'draft' | 'in_process' | 'active' | 'expired' | 'suspended' | 'terminated';
export type AgreementRenewal = 'automatic' | 'manual' | 'none';
export type AgreementActivity = 'training' | 'research' | 'extension' | 'administrative' | 'other';

/** English text of each status: the key to translate (an agreement in force is "In force", not "Active"). */
export const AGREEMENT_STATUS_LABELS: Record<AgreementStatus, string> = {
    draft: 'Draft',
    in_process: 'In process',
    active: 'In force',
    expired: 'Expired',
    suspended: 'Suspended',
    terminated: 'Terminated',
};

/** What each status means, in English: the key to translate. */
export const AGREEMENT_STATUS_DESCRIPTIONS: Record<AgreementStatus, string> = {
    draft: 'Being drafted: it has not been formally submitted yet.',
    in_process: 'Under review, in negotiation or waiting for signatures.',
    active: 'Signed and within its period of validity: mobilities can be made under it.',
    expired: 'Its end date has passed and it was not renewed.',
    suspended: 'Temporarily on hold by an administrative or contractual decision: nothing can be done under it until it resumes.',
    terminated: 'Ended for good, by agreement, decision or contractual cause.',
};

export const AGREEMENT_STATUSES = Object.keys(AGREEMENT_STATUS_LABELS) as AgreementStatus[];

export const AGREEMENT_RENEWAL_LABELS: Record<AgreementRenewal, string> = {
    automatic: 'Automatic',
    manual: 'Manual',
    none: 'Not renewable',
};

export const AGREEMENT_ACTIVITIES: readonly AgreementActivity[] = ['training', 'research', 'extension', 'administrative', 'other'];

/** English text of each activity: the key to translate. */
export const AGREEMENT_ACTIVITY_LABELS: Record<AgreementActivity, string> = {
    training: 'Training',
    research: 'Research',
    extension: 'Extension',
    administrative: 'Administrative',
    other: 'Other',
};

/** An agreement (convenio) of a partner institution with the local one. */
export type Agreement = {
    id: string;
    institution_id: string;
    institution?: Institution;
    type: AgreementType;
    parties: AgreementParties;
    /** The other institutions of a multilateral agreement (`institution` is the main one). */
    institutions?: Institution[];
    framework_id: string | null;
    framework?: { id: string; name: string; reference: string | null } | null;
    name: string;
    reference: string | null;
    starts_on: string;
    /** Null when it has no end date: indefinite. */
    ends_on: string | null;
    /** Where it stands today: as recorded, except that an agreement in force past its end date is expired. */
    status: AgreementStatus;
    recorded_status: AgreementStatus;
    purpose: string | null;
    signed_on: string | null;
    renewal: AgreementRenewal;
    /** National when every institution is in the local country: it follows from them. */
    scope: MobilityScope;
    has_document: boolean;
    document_name: string | null;
    activities: AgreementActivity[];
    other_activity: string | null;
    notes: string | null;
};

export type AgreementPayload = {
    institution_id: string;
    type: AgreementType;
    parties: AgreementParties;
    institution_ids: string[];
    framework_id: string | null;
    status: AgreementStatus;
    name: string;
    reference: string | null;
    purpose: string | null;
    signed_on: string | null;
    renewal: AgreementRenewal;
    starts_on: string;
    ends_on: string | null;
    activities: AgreementActivity[];
    other_activity: string | null;
    notes: string | null;
};

export type AgreementIndexParams = {
    page?: number;
    search?: string;
    institution_id?: string;
    type?: AgreementType;
    parties?: AgreementParties;
    status?: AgreementStatus;
    scope?: MobilityScope;
    activity?: AgreementActivity;
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
    /** The agreement it is made under, if one was chosen. */
    agreement_id?: string | null;
    agreement?: Agreement | null;
    requirements?: MobilityRequirement[];
    status: MobilityStatus;
    is_final: boolean;
    created_at: string | null;
    updated_at: string | null;
};

/** A mobility with who takes part in it, as shown in its own page. */
export type MobilityDetail = Mobility & { participant: ParticipantOption | null; agreements: Agreement[] };

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
