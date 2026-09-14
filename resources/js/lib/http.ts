import { isAxiosError } from 'axios';

const VALIDATION_STATUS = 422;

export type FieldErrors = Record<string, string>;

function propertyOf(source: unknown, key: string): unknown {
    if (typeof source !== 'object' || source === null || ! (key in source)) {
        return undefined;
    }

    return Reflect.get(source, key);
}

function validationBody(error: unknown): unknown {
    if (! isAxiosError<unknown>(error) || error.response?.status !== VALIDATION_STATUS) {
        return undefined;
    }

    return error.response.data;
}

function firstMessage(value: unknown): string | undefined {
    if (Array.isArray(value)) {
        const first: unknown = value[0];

        return typeof first === 'string' ? first : undefined;
    }

    return typeof value === 'string' ? value : undefined;
}

export function isValidationError(error: unknown): boolean {
    return isAxiosError<unknown>(error) && error.response?.status === VALIDATION_STATUS;
}

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

export function formMessageFrom(error: unknown, fallback: string): string {
    const message = propertyOf(validationBody(error), 'message');

    return typeof message === 'string' && message !== '' ? message : fallback;
}
