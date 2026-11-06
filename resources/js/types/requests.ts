export type FieldType =
    | 'text'
    | 'textarea'
    | 'number'
    | 'email'
    | 'date'
    | 'datetime'
    | 'select'
    | 'boolean';

export type RequestField = {
    key: string;
    label: string;
    type: FieldType;
    required: boolean;
    options: string[];
};

export type RequestTypeOption = {
    key: string;
    name: string;
};

export type RequestStatus =
    | 'draft'
    | 'submitted'
    | 'under_review'
    | 'approved'
    | 'rejected'
    | 'cancelled';

export type RequestSummary = {
    uuid: string;
    title: string;
    type: string;
    requester: string;
    status: RequestStatus;
    priority: string;
    created_at: string;
};

export type RequestDetail = {
    label: string;
    type: FieldType;
    value: string | number | boolean | null;
};

export type RequestShow = Omit<RequestSummary, never> & {
    description: string | null;
    submitted_at: string | null;
    details: RequestDetail[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

export type RequestEditable = {
    uuid: string;
    title: string;
    description: string | null;
    priority: string;
    status: RequestStatus;
    metadata: Record<string, string | number | boolean | null>;
};
