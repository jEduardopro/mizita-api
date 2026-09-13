import { isAxiosError } from 'axios';

/**
 * The status Laravel answers a failed FormRequest with, and the only one that
 * carries a per-field body. Everything else is either a decision the screen
 * handles itself or a fault nobody should be shown the internals of.
 */
const VALIDATION_STATUS = 422;

/**
 * One message per field, keyed by the name the FormRequest validated.
 *
 * This is deliberately the same shape Inertia hands a page in `form.errors`, so
 * a 422 that arrives through axios renders identically to one that arrives from
 * a Fortify post: both end up as a single string under a field name.
 */
export type FieldErrors = Record<string, string>;

/**
 * Reads a property off a value that is only known to be `unknown`.
 *
 * The error body is whatever the server sent, so every step into it has to be
 * guarded. Doing it here keeps the guards in one place instead of repeating a
 * cast at each level of the envelope.
 */
function propertyOf(source: unknown, key: string): unknown {
    if (typeof source !== 'object' || source === null || ! (key in source)) {
        return undefined;
    }

    return Reflect.get(source, key);
}

/** The body of a validation failure, or `undefined` for anything else. */
function validationBody(error: unknown): unknown {
    if (! isAxiosError<unknown>(error) || error.response?.status !== VALIDATION_STATUS) {
        return undefined;
    }

    return error.response.data;
}

/** The first string in `['too long', 'already taken']`, or the value itself. */
function firstMessage(value: unknown): string | undefined {
    if (Array.isArray(value)) {
        const first: unknown = value[0];

        return typeof first === 'string' ? first : undefined;
    }

    return typeof value === 'string' ? value : undefined;
}

/** Whether the server rejected the payload field by field. */
export function isValidationError(error: unknown): boolean {
    return isAxiosError<unknown>(error) && error.response?.status === VALIDATION_STATUS;
}

/**
 * The field messages from a 422, one per field, and an empty map for anything
 * else — a network fault has nothing to say about a particular input.
 *
 * Laravel sends every failing rule for a field; only the first is kept. A person
 * fixes one thing at a time, and the second message is usually a consequence of
 * the first.
 */
export function fieldErrorsFrom(error: unknown): FieldErrors {
    const errors = propertyOf(validationBody(error), 'errors');

    if (typeof errors !== 'object' || errors === null) {
        return {};
    }

    const fieldErrors: FieldErrors = {};

    for (const field of Object.keys(errors)) {
        const message = firstMessage(propertyOf(errors, field));

        if (message !== undefined) {
            fieldErrors[field] = message;
        }
    }

    return fieldErrors;
}

/**
 * The message to show above the form.
 *
 * A 422 carries the server's own sentence, already written in the caller's
 * language because `lib/api.ts` sends `X-Locale` on every request — so it is
 * shown as it arrived. Anything else falls back to the caller's copy: a 500 body
 * is a stack trace or a framework string, never something to put in front of a
 * person.
 */
export function formMessageFrom(error: unknown, fallback: string): string {
    const message = propertyOf(validationBody(error), 'message');

    return typeof message === 'string' && message !== '' ? message : fallback;
}
