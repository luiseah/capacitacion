import type { FieldType, RequestField, RequestStatus } from './requests';

/** Envelope returned by the backend's `Response::api()` / `Response::success()`. */
export type ApiResponse<T = unknown> = {
    status: 'success';
    message?: string;
    data: T;
};

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    links?: { url: string | null; label: string; active: boolean }[];
};

export type PaginatedApiResponse<T> = ApiResponse<T[]> & PaginationMeta;

/** A page of items as exposed by the services (envelope already unwrapped). */
export type PageResult<T> = {
    items: T[];
    meta: PaginationMeta;
};

export type ApiRequestTypeDefinition = {
    key: string;
    name: string;
    description: string | null;
    fields: RequestField[];
};

export type ApiRequestSummary = {
    uuid: string;
    title: string;
    type: string;
    type_key: string;
    requester: string;
    status: RequestStatus;
    priority: string;
    created_at: string;
};

export type ApiRequestDetail = ApiRequestSummary & {
    description: string | null;
    submitted_at: string | null;
    details: {
        key: string;
        label: string;
        type: FieldType;
        value: string | number | boolean | null;
    }[];
};

export type RequestPayload = {
    title: string;
    description?: string | null;
    priority: string;
    action?: 'draft' | 'submit';
    metadata: Record<string, unknown>;
};

export type RequestIndexParams = {
    page?: number;
};
