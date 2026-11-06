import ApiError from './http/ApiError';
import type {
    ApiResponse,
    NoExtras,
    PageResult,
    PaginationMeta,
} from '@/types';

export type FromOptions<E extends object> = {
    /** Extra fields the endpoint must send; a missing one is a malformed response. */
    extras?: readonly (keyof E & string)[];
};

/** The pagination fields every paginated response must carry. */
export const paginationKeys = [
    'current_page',
    'last_page',
    'per_page',
    'total',
] as const satisfies readonly (keyof PaginationMeta)[];

/**
 * Typed wrapper of the backend's response envelope
 * (`{ status, message?, data, ...extras }`, see `ApiResponse`).
 *
 * `T` is the type of `data` and `E` the type of the endpoint's extra fields, so
 * services declare what an endpoint returns once and read it without casts.
 */
export default class ResponseService<T = null, E extends object = NoExtras> {
    private readonly body: ApiResponse<T, E>;

    private constructor(body: ApiResponse<T, E>) {
        this.body = body;
    }

    /**
     * Wrap a body, failing if it is not a success envelope or lacks a required extra.
     */
    static from<T = null, E extends object = NoExtras>(
        body: unknown,
        options: FromOptions<E> = {},
    ): ResponseService<T, E> {
        if (
            typeof body !== 'object' ||
            body === null ||
            (body as { status?: unknown }).status !== 'success'
        ) {
            throw new ApiError(500, 'Unexpected response from the server', {}, body);
        }

        const missing = (options.extras ?? []).filter((key) => !(key in body));

        if (missing.length > 0) {
            throw new ApiError(
                500,
                `The response is missing: ${missing.join(', ')}`,
                {},
                body,
            );
        }

        return new ResponseService<T, E>(body as ApiResponse<T, E>);
    }

    get data(): T {
        return this.body.data;
    }

    get message(): string | undefined {
        return this.body.message;
    }

    /** The endpoint's own fields (everything besides `status`, `message` and `data`). */
    get extras(): E {
        const { status: _status, message: _message, data: _data, ...extras } = this.body;

        return extras as unknown as E;
    }

    /** A paginated response as `{ items, meta }`. */
    toPage<Item>(this: ResponseService<Item[], PaginationMeta>): PageResult<Item> {
        return { items: this.data, meta: this.extras };
    }
}
