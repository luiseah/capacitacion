import type { FieldType, RequestField, RequestStatus } from './requests';

/** No extra fields besides the envelope ones. */
export type NoExtras = Record<never, never>;

/**
 * The single shape of every successful backend response (`Response::api()` /
 * `Response::success()`): the envelope plus the endpoint's own typed extras.
 *
 * @example ApiResponse<ApiRequestTypeDefinition[], { priorities: string[] }>
 */
export type ApiResponse<T = null, E extends object = NoExtras> = {
    status: 'success';
    message?: string;
    data: T;
} & E;

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

export type PaginatedApiResponse<T> = ApiResponse<T[], PaginationMeta>;

/** Extra fields of the request types endpoint. */
export type RequestTypesExtras = { priorities: string[] };

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
    abilities: { update: boolean; delete: boolean };
    details: {
        key: string;
        label: string;
        type: FieldType;
        value: string | number | boolean | null;
    }[];
};

/** Request types with their fields plus the priorities a request can have. */
export type RequestTypeCatalog = {
    types: ApiRequestTypeDefinition[];
    priorities: string[];
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
    /** Relations to include: the ones the list shows (`type`, `requester`). */
    with?: string[];
};

export type ApiUser = {
    id: number;
    name: string;
    lastname: string;
    full_name: string;
    email: string;
    phone: string | null;
    roles: string[];
    email_verified: boolean;
    abilities: { update: boolean; delete: boolean };
    created_at: string;
};

export type SortDirection = 'asc' | 'desc';

export type UserSortColumn = 'name' | 'lastname' | 'email' | 'phone' | 'verified' | 'created_at';

export type UserIndexParams = {
    page?: number;
    search?: string;
    role?: string;
    /** Relations to include (`roles`). */
    with?: string[];
    sort?: Partial<Record<UserSortColumn, SortDirection>>;
};

export type UserPayload = {
    name: string;
    lastname: string;
    email: string;
    phone: string | null;
    roles: string[];
};
