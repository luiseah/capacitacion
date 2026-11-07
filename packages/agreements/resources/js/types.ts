import type { Institution, MobilityScope } from '@academic/types';

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
    /** Relations to include: the ones the list shows. */
    with?: ('institution' | 'institutions' | 'framework')[];
    sort?: Partial<Record<'name' | 'starts_on' | 'ends_on' | 'status' | 'created_at', 'asc' | 'desc'>>;
};
