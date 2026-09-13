import { useCallback, useMemo, useState } from 'react';
import { fieldErrorsFrom, formMessageFrom, type FieldErrors } from '@/lib/http';

type ServerErrors = {
    /** One message per field, ready to hand to the field of that name. */
    fieldErrors: FieldErrors;
    /** The sentence to show above the form, or null while nothing has failed. */
    formMessage: string | null;
    /** Records a rejected request. `fallback` is the copy for a non-422. */
    capture(error: unknown, fallback: string): void;
    /** Drops one field's message, e.g. as soon as that field is edited. */
    clearField(name: string): void;
    /** Clears everything, e.g. when a submission starts. */
    reset(): void;
};

/**
 * Holds what the server said about the last submission.
 *
 * This is the replacement for `react-hook-form`'s `setError`, and it exists
 * because the backend's FormRequest is the only authority on what is valid: the
 * front end never decides a value is wrong, it only renders the answer. So there
 * is no schema and no client rule here, just the two halves of a 422 — the
 * per-field messages and the form-level sentence.
 *
 * `capture` fills both halves from whatever arrived. A field message the form
 * does not render would otherwise vanish silently, which is why the form-level
 * message is always set too: the caller decides what to do with a key it has no
 * input for, and the person is never left looking at a form that simply refused.
 */
export function useServerErrors(): ServerErrors {
    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
    const [formMessage, setFormMessage] = useState<string | null>(null);

    const capture = useCallback((error: unknown, fallback: string) => {
        setFieldErrors(fieldErrorsFrom(error));
        setFormMessage(formMessageFrom(error, fallback));
    }, []);

    const clearField = useCallback((name: string) => {
        setFieldErrors((current) => {
            if (! (name in current)) {
                return current;
            }

            const remaining: FieldErrors = {};

            for (const field of Object.keys(current)) {
                if (field !== name) {
                    remaining[field] = current[field];
                }
            }

            return remaining;
        });
    }, []);

    const reset = useCallback(() => {
        setFieldErrors({});
        setFormMessage(null);
    }, []);

    return useMemo(
        () => ({ fieldErrors, formMessage, capture, clearField, reset }),
        [fieldErrors, formMessage, capture, clearField, reset],
    );
}
