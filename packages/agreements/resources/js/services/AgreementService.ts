import AbstractService from '@/services/AbstractService';
import type ResponseService from '@/services/ResponseService';
import type { PaginationMeta } from '@academic/types';
import type { Agreement, AgreementIndexParams, AgreementPayload } from '@agreements/types';

type ReadOptions = { signal?: AbortSignal };

/** Client of the agreements JSON API. Like the mobility one, it is not in the host's service registry. */
export class AgreementService extends AbstractService {
    /** The agreements of an institution with the local one (the latest first). */
    public async of(institutionId: string, options: ReadOptions = {}): Promise<ResponseService<Agreement[]>> {
        return this.client.request({
            method: 'GET',
            url: '/api/agreements/institution',
            params: { institution_id: institutionId },
            signal: options.signal,
        });
    }

    public async all(
        params: AgreementIndexParams = {},
        options: ReadOptions = {},
    ): Promise<ResponseService<Agreement[], PaginationMeta>> {
        return this.client.paginated<Agreement>({
            method: 'GET',
            url: '/api/agreements',
            params,
            signal: options.signal,
        });
    }

    public async find(id: string, options: ReadOptions = {}): Promise<ResponseService<Agreement>> {
        return this.client.request({
            method: 'GET',
            url: `/api/agreements/${encodeURIComponent(id)}`,
            signal: options.signal,
        });
    }

    public async store(payload: AgreementPayload): Promise<ResponseService<Agreement>> {
        return this.client.request({ method: 'POST', url: '/api/agreements', data: payload });
    }

    public async update(id: string, payload: AgreementPayload): Promise<ResponseService<Agreement>> {
        return this.client.request({
            method: 'PUT',
            url: `/api/agreements/${encodeURIComponent(id)}`,
            data: payload,
        });
    }

    /** Keeps the signed document (a PDF) of an agreement, replacing the one it had. */
    public async uploadDocument(id: string, file: File): Promise<ResponseService<Agreement>> {
        const data = new FormData();
        data.append('document', file);

        return this.client.request({
            method: 'POST',
            url: `/api/agreements/${encodeURIComponent(id)}/document`,
            data,
        });
    }

    public async removeDocument(id: string): Promise<ResponseService> {
        return this.client.request({
            method: 'DELETE',
            url: `/api/agreements/${encodeURIComponent(id)}/document`,
        });
    }

    public async destroy(id: string): Promise<ResponseService> {
        return this.client.request({
            method: 'DELETE',
            url: `/api/agreements/${encodeURIComponent(id)}`,
        });
    }
}

/** Where the signed document of an agreement is downloaded from. */
export const agreementDocumentUrl = (id: string): string => `/api/agreements/${encodeURIComponent(id)}/document`;

export const agreementService = new AgreementService();
