import HttpClient, { httpClient } from './http/HttpClient';

/**
 * Base class of every API service. Subclasses talk to the backend only through
 * `this.client`, so the transport can be swapped or mocked in one place.
 */
export default abstract class AbstractService {
    protected readonly client: HttpClient;

    constructor(client: HttpClient = httpClient) {
        this.client = client;
    }
}
