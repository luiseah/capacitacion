import type { RequestField, RequestPayload } from '@/types';

/**
 * Builds the API payload from the submitted form. Inputs named
 * `metadata[<key>]` become `metadata.<key>`; checkboxes send `true` when
 * checked and `false` otherwise; empty values are sent as `null`.
 */
export function buildRequestPayload(
    form: FormData,
    fields: RequestField[],
    action: 'draft' | 'submit',
): RequestPayload {
    const metadata: Record<string, unknown> = {};

    for (const field of fields) {
        const name = `metadata[${field.key}]`;

        if (field.type === 'boolean') {
            metadata[field.key] = form.has(name);
            continue;
        }

        const value = form.get(name);
        metadata[field.key] = typeof value === 'string' && value !== '' ? value : null;
    }

    const text = (name: string) => {
        const value = form.get(name);

        return typeof value === 'string' ? value : '';
    };

    return {
        title: text('title'),
        description: text('description') || null,
        priority: text('priority'),
        action,
        metadata,
    };
}

/** Keeps the first message of every field, the shape the inputs display. */
export function firstErrors(errors: Record<string, string[]>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(errors).map(([field, messages]) => [field, messages[0]]),
    );
}
