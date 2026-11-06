/**
 * Error thrown for every non-2xx response. Validation failures (422) expose
 * the per-field messages sent by Laravel in `errors`.
 */
export default class ApiError extends Error {
    readonly status: number;
    readonly errors: Record<string, string[]>;
    readonly body: unknown;

    constructor(
        status: number,
        message: string,
        errors: Record<string, string[]> = {},
        body: unknown = null,
    ) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
        this.body = body;
    }

    get isValidation(): boolean {
        return this.status === 422;
    }

    /** First validation message of a field, e.g. `metadata.currency`. */
    firstError(field: string): string | undefined {
        return this.errors[field]?.[0];
    }
}
