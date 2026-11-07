import AbstractService from '@/services/AbstractService';
import type ResponseService from '@/services/ResponseService';
import type {
    Agreement,
    AgreementIndexParams,
    AgreementPayload,
    Institution,
    InstitutionIndexParams,
    InstitutionPayload,
    Mobility,
    MobilityDirection,
    MobilityDetail,
    MobilityIndexParams,
    MobilityOptions,
    MobilityPayload,
    MobilityScope,
    Requirement,
    RequirementIndexParams,
    RequirementPayload,
    ParticipantKind,
    ParticipantOption,
    PaginationMeta,
} from '@academic/types';

type ReadOptions = { signal?: AbortSignal };

/**
 * Client of the package's JSON API. Not registered in the host's service
 * registry on purpose: the package owns it and exports one shared instance.
 */
export class MobilityService extends AbstractService {
    public async all(
        params: MobilityIndexParams = {},
        options: ReadOptions = {},
    ): Promise<ResponseService<Mobility[], PaginationMeta>> {
        return this.client.paginated<Mobility>({
            method: 'GET',
            url: '/api/academic-mobility/mobilities',
            params,
            signal: options.signal,
        });
    }

    public async options(options: ReadOptions = {}): Promise<ResponseService<MobilityOptions>> {
        return this.client.request({
            method: 'GET',
            url: '/api/academic-mobility/mobilities/options',
            signal: options.signal,
        });
    }

    /** Partner institutions of a scope matching a search: the picker of the creation form. */
    public async institutionOptions(
        params: { scope: MobilityScope | ''; search: string },
        options: ReadOptions = {},
    ): Promise<ResponseService<Institution[]>> {
        return this.client.request({
            method: 'GET',
            url: '/api/academic-mobility/mobilities/institutions',
            params: { scope: params.scope || undefined, search: params.search },
            signal: options.signal,
        });
    }

    public async participants(
        params: { type: ParticipantKind; direction: MobilityDirection; institutionId?: string; search: string },
        options: ReadOptions = {},
    ): Promise<ResponseService<ParticipantOption[]>> {
        return this.client.request({
            method: 'GET',
            url: '/api/academic-mobility/mobilities/participants',
            params: {
                type: params.type,
                direction: params.direction,
                institution_id: params.institutionId || undefined,
                search: params.search,
            },
            signal: options.signal,
        });
    }

    public async mobility(id: string, options: ReadOptions = {}): Promise<ResponseService<MobilityDetail>> {
        return this.client.request({
            method: 'GET',
            url: `/api/academic-mobility/mobilities/${encodeURIComponent(id)}`,
            signal: options.signal,
        });
    }

    /** The agreements of an institution with the local one (the latest first). */
    public async agreementsOf(institutionId: string, options: ReadOptions = {}): Promise<ResponseService<Agreement[]>> {
        return this.client.request({
            method: 'GET',
            url: '/api/academic-mobility/mobilities/agreements',
            params: { institution_id: institutionId },
            signal: options.signal,
        });
    }

    public async agreements(
        params: AgreementIndexParams = {},
        options: ReadOptions = {},
    ): Promise<ResponseService<Agreement[], PaginationMeta>> {
        return this.client.paginated<Agreement>({
            method: 'GET',
            url: '/api/academic-mobility/agreements',
            params,
            signal: options.signal,
        });
    }

    public async agreement(id: string, options: ReadOptions = {}): Promise<ResponseService<Agreement>> {
        return this.client.request({
            method: 'GET',
            url: `/api/academic-mobility/agreements/${encodeURIComponent(id)}`,
            signal: options.signal,
        });
    }

    public async storeAgreement(payload: AgreementPayload): Promise<ResponseService<Agreement>> {
        return this.client.request({ method: 'POST', url: '/api/academic-mobility/agreements', data: payload });
    }

    public async updateAgreement(id: string, payload: AgreementPayload): Promise<ResponseService<Agreement>> {
        return this.client.request({
            method: 'PUT',
            url: `/api/academic-mobility/agreements/${encodeURIComponent(id)}`,
            data: payload,
        });
    }

    /** Keeps the signed document (a PDF) of an agreement, replacing the one it had. */
    public async uploadAgreementDocument(id: string, file: File): Promise<ResponseService<Agreement>> {
        const data = new FormData();
        data.append('document', file);

        return this.client.request({
            method: 'POST',
            url: `/api/academic-mobility/agreements/${encodeURIComponent(id)}/document`,
            data,
        });
    }

    public async removeAgreementDocument(id: string): Promise<ResponseService> {
        return this.client.request({
            method: 'DELETE',
            url: `/api/academic-mobility/agreements/${encodeURIComponent(id)}/document`,
        });
    }

    public async destroyAgreement(id: string): Promise<ResponseService> {
        return this.client.request({
            method: 'DELETE',
            url: `/api/academic-mobility/agreements/${encodeURIComponent(id)}`,
        });
    }

    /** What a mobility with this direction, scope and participant kind will have to bring. */
    public async requirementsFor(
        params: { direction: MobilityDirection; scope: MobilityScope; type: ParticipantKind },
        options: ReadOptions = {},
    ): Promise<ResponseService<Requirement[]>> {
        return this.client.request({
            method: 'GET',
            url: '/api/academic-mobility/mobilities/requirements',
            params,
            signal: options.signal,
        });
    }

    public async requirements(
        params: RequirementIndexParams = {},
        options: ReadOptions = {},
    ): Promise<ResponseService<Requirement[], PaginationMeta>> {
        return this.client.paginated<Requirement>({
            method: 'GET',
            url: '/api/academic-mobility/requirements',
            params,
            signal: options.signal,
        });
    }

    public async requirement(id: string, options: ReadOptions = {}): Promise<ResponseService<Requirement>> {
        return this.client.request({
            method: 'GET',
            url: `/api/academic-mobility/requirements/${encodeURIComponent(id)}`,
            signal: options.signal,
        });
    }

    public async storeRequirement(payload: RequirementPayload): Promise<ResponseService<Requirement>> {
        return this.client.request({ method: 'POST', url: '/api/academic-mobility/requirements', data: payload });
    }

    public async updateRequirement(id: string, payload: RequirementPayload): Promise<ResponseService<Requirement>> {
        return this.client.request({
            method: 'PUT',
            url: `/api/academic-mobility/requirements/${encodeURIComponent(id)}`,
            data: payload,
        });
    }

    public async destroyRequirement(id: string): Promise<ResponseService> {
        return this.client.request({
            method: 'DELETE',
            url: `/api/academic-mobility/requirements/${encodeURIComponent(id)}`,
        });
    }

    /** Random values for the registration form, invented by the host (demos and development). */
    public async sampleParticipant(
        params: { type: ParticipantKind; institutionId: string },
    ): Promise<ResponseService<Record<string, string>>> {
        return this.client.request({
            method: 'GET',
            url: '/api/academic-mobility/mobilities/participants/sample',
            params: { type: params.type, direction: 'incoming', institution_id: params.institutionId },
        });
    }

    /** Registers a person that does not exist yet (a visitor of the origin institution). */
    public async storeParticipant(payload: {
        type: ParticipantKind;
        direction: MobilityDirection;
        institution_id: string;
        data: Record<string, string>;
    }): Promise<ResponseService<ParticipantOption>> {
        return this.client.request({ method: 'POST', url: '/api/academic-mobility/mobilities/participants', data: payload });
    }

    public async store(payload: MobilityPayload): Promise<ResponseService<Mobility>> {
        return this.client.request({
            method: 'POST',
            url: '/api/academic-mobility/mobilities',
            data: payload,
        });
    }

    public async institutions(
        params: InstitutionIndexParams = {},
        options: ReadOptions = {},
    ): Promise<ResponseService<Institution[], PaginationMeta>> {
        return this.client.paginated<Institution>({
            method: 'GET',
            url: '/api/academic-mobility/institutions',
            params,
            signal: options.signal,
        });
    }

    public async institution(id: string, options: ReadOptions = {}): Promise<ResponseService<Institution>> {
        return this.client.request({
            method: 'GET',
            url: `/api/academic-mobility/institutions/${encodeURIComponent(id)}`,
            signal: options.signal,
        });
    }

    public async storeInstitution(payload: InstitutionPayload): Promise<ResponseService<Institution>> {
        return this.client.request({ method: 'POST', url: '/api/academic-mobility/institutions', data: payload });
    }

    public async updateInstitution(id: string, payload: InstitutionPayload): Promise<ResponseService<Institution>> {
        return this.client.request({
            method: 'PUT',
            url: `/api/academic-mobility/institutions/${encodeURIComponent(id)}`,
            data: payload,
        });
    }

    public async destroyInstitution(id: string): Promise<ResponseService> {
        return this.client.request({
            method: 'DELETE',
            url: `/api/academic-mobility/institutions/${encodeURIComponent(id)}`,
        });
    }
}

/** Where the signed document of an agreement is downloaded from. */
export const agreementDocumentUrl = (id: string): string => `/api/academic-mobility/agreements/${encodeURIComponent(id)}/document`;

export const mobilityService = new MobilityService();
