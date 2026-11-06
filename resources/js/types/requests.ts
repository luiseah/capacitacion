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

export type RequestStatus =
    | 'draft'
    | 'submitted'
    | 'under_review'
    | 'approved'
    | 'rejected'
    | 'cancelled';
