import { describe, expect, it } from 'vitest';
import { buildRequestPayload, firstErrors } from '@/lib/request-payload';
import type { RequestField } from '@/types';

const fields: RequestField[] = [
    { key: 'reason', label: 'Reason', type: 'textarea', required: true, options: [] },
    { key: 'currency', label: 'Currency', type: 'select', required: true, options: ['USD'] },
    { key: 'optional', label: 'Optional', type: 'text', required: false, options: [] },
    { key: 'signed', label: 'Signed', type: 'boolean', required: false, options: [] },
];

function form(values: Record<string, string>): FormData {
    const data = new FormData();
    Object.entries(values).forEach(([key, value]) => data.append(key, value));

    return data;
}

describe('buildRequestPayload', () => {
    it('maps the form to the API payload', () => {
        const payload = buildRequestPayload(
            form({
                title: 'Laptops',
                description: '',
                priority: 'high',
                'metadata[reason]': 'Growth',
                'metadata[currency]': 'USD',
                'metadata[signed]': '1',
            }),
            fields,
            'submit',
        );

        expect(payload).toEqual({
            title: 'Laptops',
            description: null,
            priority: 'high',
            action: 'submit',
            metadata: { reason: 'Growth', currency: 'USD', optional: null, signed: true },
        });
    });

    it('sends false for an unchecked checkbox and ignores unknown inputs', () => {
        const payload = buildRequestPayload(
            form({ title: 'x', priority: 'low', 'metadata[other]': 'ignored' }),
            fields,
            'draft',
        );

        expect(payload.metadata).toEqual({ reason: null, currency: null, optional: null, signed: false });
    });
});

describe('firstErrors', () => {
    it('keeps the first message of every field', () => {
        expect(firstErrors({ title: ['Required', 'Too short'], 'metadata.currency': ['Invalid'] })).toEqual({
            title: 'Required',
            'metadata.currency': 'Invalid',
        });
    });
});
