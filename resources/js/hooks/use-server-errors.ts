import { useCallback, useMemo, useState } from 'react';
import { useErrorToast } from '@/hooks/use-error-toast';
import { fieldErrorsFrom, formMessageFrom, type FieldErrors } from '@/lib/http';

type ServerErrors = {
    fieldErrors: FieldErrors;
    capture(error: unknown, fallback: string): void;
    clearField(name: string): void;
    reset(): void;
};

export function useServerErrors(): ServerErrors {
    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
    const errorToast = useErrorToast();

    const capture = useCallback(
        (error: unknown, fallback: string) => {
            setFieldErrors(fieldErrorsFrom(error));
            errorToast.show(formMessageFrom(error, fallback));
        },
        [errorToast],
    );

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
        errorToast.dismiss();
    }, [errorToast]);

    return useMemo(
        () => ({ fieldErrors, capture, clearField, reset }),
        [fieldErrors, capture, clearField, reset],
    );
}
