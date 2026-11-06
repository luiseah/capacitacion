import { createTypedFactoryProxy } from '@/lib/utils';
import RequestService from './RequestService';
import RequestTypeService from './RequestTypeService';

export { default as ApiError } from './http/ApiError';
export { default as HttpClient, httpClient } from './http/HttpClient';
export { default as AbstractService } from './AbstractService';

/**
 * Registry map with all API services.
 * Types are inferred automatically - no need for a separate interface.
 * To add a service, register its class here.
 */
const registry = {
    requests: RequestService,
    requestTypes: RequestTypeService,
} as const;

/**
 * Lazy, typed container: each service is instantiated on first access and
 * reused afterwards (see `createTypedFactoryProxy`).
 */
const services = createTypedFactoryProxy(registry);

export type Services = typeof services;

export default services;
