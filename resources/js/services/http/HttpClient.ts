import axios from 'axios';
import type { AxiosInstance } from 'axios';
import ApiError from './ApiError';

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export type QueryParams = Record<string, unknown>;

export type RequestConfig = {
    method?: HttpMethod;
    /** Path relative to the base URL, or an absolute URL (see `buildUrl`). */
    url: string;
    /** Query string parameters. */
    params?: QueryParams;
    /** JSON body. */
    data?: unknown;
    headers?: Record<string, string>;
    /** Lets callers (e.g. TanStack Query) cancel the request. */
    signal?: AbortSignal;
};

export type HttpClientOptions = {
    baseUrl?: string;
    timeoutMs?: number;
    /** Return a token to authenticate with `Authorization: Bearer`. Optional: the SPA uses the session cookie. */
    getToken?: () => string | null | undefined;
    /** Custom axios instance, mainly to inject an adapter in tests. */
    instance?: AxiosInstance;
};

type ErrorBody = {
    message?: string;
    errors?: Record<string, string[]>;
};

/**
 * Thin axios wrapper for the Laravel API. Axios forwards the `XSRF-TOKEN`
 * cookie as `X-XSRF-TOKEN` (Sanctum stateful auth), this class adds the optional
 * bearer token and normalizes every failure into an `ApiError`.
 */
export default class HttpClient {
    private readonly axios: AxiosInstance;

    constructor(options: HttpClientOptions = {}) {
        this.axios =
            options.instance ??
            axios.create({
                baseURL: options.baseUrl,
                timeout: options.timeoutMs ?? 30_000,
            });

        this.axios.defaults.headers.common.Accept = 'application/json';
        this.axios.defaults.headers.common['X-Requested-With'] =
            'XMLHttpRequest';

        const getToken = options.getToken;

        if (getToken) {
            this.axios.interceptors.request.use((config) => {
                const token = getToken();

                if (token) {
                    config.headers.Authorization = `Bearer ${token}`;
                }

                return config;
            });
        }

        this.axios.interceptors.response.use(
            (response) => response,
            (error: unknown) => {
                throw this.normalizeError(error);
            },
        );
    }

    /**
     * Replace `/:name` placeholders with `params` and append `query`.
     *
     * @example buildUrl({ url: '/api/v1/requests/:id', params: { id: 'abc' } })
     */
    buildUrl({
        url,
        params = {},
        query = {},
    }: {
        url: string;
        params?: Record<string, string | number>;
        query?: QueryParams;
    }): string {
        const path = url.replace(/\/:(\w+)/g, (_match, name: string) => {
            if (!(name in params)) {
                throw new Error(`Missing URL parameter "${name}" for ${url}`);
            }

            return `/${encodeURIComponent(String(params[name]))}`;
        });

        const search = new URLSearchParams();

        for (const [key, value] of Object.entries(query)) {
            if (value === undefined || value === null) {
                continue;
            }

            if (Array.isArray(value)) {
                value.forEach((item) => search.append(`${key}[]`, String(item)));
            } else {
                search.append(key, String(value));
            }
        }

        const queryString = search.toString();

        return queryString ? `${path}?${queryString}` : path;
    }

    async request<T = unknown>(config: RequestConfig): Promise<T> {
        const response = await this.axios.request<T>({
            method: config.method ?? 'GET',
            url: config.url,
            params: config.params,
            data: config.data,
            headers: config.headers,
            signal: config.signal,
        });

        return response.data;
    }

    private normalizeError(error: unknown): unknown {
        // Cancellations are not failures: let them propagate untouched.
        if (axios.isCancel(error) || !axios.isAxiosError(error)) {
            return error;
        }

        if (error.response) {
            const body = (error.response.data ?? {}) as ErrorBody;

            return new ApiError(
                error.response.status,
                body.message ?? error.message,
                body.errors,
                error.response.data,
            );
        }

        if (error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT') {
            return new ApiError(408, 'The request timed out');
        }

        return new ApiError(0, 'Network error');
    }
}

export const httpClient = new HttpClient();
