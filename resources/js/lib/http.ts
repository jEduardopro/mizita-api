import { isAxiosError } from 'axios';
import type { ApiWarning } from '@/types/api';

const VALIDATION_STATUS = 422;

export type FieldErrors = Record<string, string>;

function propertyOf(source: unknown, key: string): unknown {
    if (typeof source !== 'object' || source === null || ! (key in source)) {
        return undefined;
    }

    return Reflect.get(source, key);
}

function firstMessage(value: unknown): string | undefined {
    if (Array.isArray(value)) {
        const first: unknown = value[0];

        return typeof first === 'string' ? first : undefined;
    }

    return typeof value === 'string' ? value : undefined;
}

function isWarning(value: unknown): value is ApiWarning {
    return (
        typeof propertyOf(value, 'code') === 'string' &&
        typeof propertyOf(value, 'message') === 'string'
    );
}

function codeOf(body: unknown): string | undefined {
    const code = propertyOf(body, 'code');

    return typeof code === 'string' && code !== '' ? code : undefined;
}

function validationErrorsOf(body: unknown): object | undefined {
    const errors = propertyOf(body, 'errors');

    return typeof errors === 'object' && errors !== null ? errors : undefined;
}

function carriesTranslatedMessage(body: unknown): boolean {
    return codeOf(body) !== undefined || validationErrorsOf(body) !== undefined;
}

export function responseBodyFrom(error: unknown): unknown {
    if (! isAxiosError<unknown>(error) || error.response === undefined) {
        return undefined;
    }

    return error.response.data;
}

export function isValidationError(error: unknown): boolean {
    return httpStatusFrom(error) === VALIDATION_STATUS;
}

export function httpStatusFrom(error: unknown): number | undefined {
    return isAxiosError<unknown>(error) ? error.response?.status : undefined;
}

export function fieldErrorsFrom(error: unknown): FieldErrors {
    const errors = validationErrorsOf(responseBodyFrom(error));

    if (errors === undefined) {
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
    const body = responseBodyFrom(error);

    if (! carriesTranslatedMessage(body)) {
        return fallback;
    }

    const message = propertyOf(body, 'message');

    return typeof message === 'string' && message !== '' ? message : fallback;
}

export function errorCodeFrom(error: unknown): string | undefined {
    return codeOf(responseBodyFrom(error));
}

export function warningsFrom(payload: unknown): ApiWarning[] {
    const warnings = propertyOf(payload, 'warnings');

    if (! Array.isArray(warnings)) {
        return [];
    }

    const entries: unknown[] = warnings;

    return entries.filter(isWarning);
}
