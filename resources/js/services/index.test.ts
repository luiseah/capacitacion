import { describe, expect, it } from 'vitest';
import { createTypedFactoryProxy } from '@/lib/utils';
import services from './index';
import RequestService from './RequestService';
import RequestTypeService from './RequestTypeService';

describe('services container', () => {
    it('exposes every registered service with its type', () => {
        expect(services.requests).toBeInstanceOf(RequestService);
        expect(services.requestTypes).toBeInstanceOf(RequestTypeService);
    });

    it('reuses the same instance on every access', () => {
        expect(services.requests).toBe(services.requests);
    });

    it('throws for a key that is not registered', () => {
        expect(() => (services as unknown as Record<string, unknown>).nope).toThrow(
            'Service not found for key: nope',
        );
    });
});

describe('createTypedFactoryProxy', () => {
    it('instantiates each class lazily and only once', () => {
        let created = 0;

        class Counter {
            constructor() {
                created += 1;
            }
        }

        const proxy = createTypedFactoryProxy({ counter: Counter });

        expect(created).toBe(0);
        expect(proxy.counter).toBe(proxy.counter);
        expect(created).toBe(1);
    });
});
