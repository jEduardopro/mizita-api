import { useCallback, useMemo, useState } from 'react';
import { useErrorToast } from '@/hooks/use-error-toast';
import { errorCodeFrom } from '@/lib/http';

export type AddressClearingField = 'street' | 'city' | 'postalCode';

const ADDRESS_CLEARING_FIELDS: AddressClearingField[] = ['street', 'city', 'postalCode'];

export type StoredAddress = {
    street: string;
    city: string | null;
    postal_code: string | null;
};

export type AddressClearingValues = Record<AddressClearingField, string>;

const STORED_ADDRESS_KEYS: Record<AddressClearingField, keyof StoredAddress> = {
    street: 'street',
    city: 'city',
    postalCode: 'postal_code',
};

const CLEARING_REFUSAL_CODES: Record<string, AddressClearingField> = {
    address_city_cannot_be_cleared: 'city',
    address_postal_code_cannot_be_cleared: 'postalCode',
};

export type AddressClearingGuard = {
    isRequired: (field: string) => boolean;
    errorFor: (field: string) => string | undefined;
    confirmSaveAllowed: () => boolean;
    captureRefusal: (error: unknown) => void;
};

type Params = {
    stored: StoredAddress | null;
    values: AddressClearingValues;
    fieldMessage: string;
    blockedMessage: string;
};

function isAddressClearingField(field: string): field is AddressClearingField {
    return ADDRESS_CLEARING_FIELDS.some((candidate) => candidate === field);
}

function storedValue(stored: StoredAddress | null, field: AddressClearingField): string {
    const value = stored === null ? null : stored[STORED_ADDRESS_KEYS[field]];

    return (value ?? '').trim();
}

function refusedFieldFrom(error: unknown): AddressClearingField | undefined {
    const code = errorCodeFrom(error);

    return code === undefined ? undefined : CLEARING_REFUSAL_CODES[code];
}

export function useAddressClearingGuard({
    stored,
    values,
    fieldMessage,
    blockedMessage,
}: Params): AddressClearingGuard {
    const warning = useErrorToast();
    const [refusedFields, setRefusedFields] = useState<AddressClearingField[]>([]);

    const requiredFields = useMemo(
        () =>
            ADDRESS_CLEARING_FIELDS.filter(
                (field) =>
                    storedValue(stored, field) !== '' ||
                    refusedFields.some((refused) => refused === field),
            ),
        [stored, refusedFields],
    );

    const clearedFields = useMemo(
        () => requiredFields.filter((field) => values[field].trim() === ''),
        [requiredFields, values],
    );

    const isRequired = useCallback(
        (field: string) =>
            isAddressClearingField(field) && requiredFields.some((required) => required === field),
        [requiredFields],
    );

    const errorFor = useCallback(
        (field: string) =>
            isAddressClearingField(field) && clearedFields.some((cleared) => cleared === field)
                ? fieldMessage
                : undefined,
        [clearedFields, fieldMessage],
    );

    const confirmSaveAllowed = useCallback(() => {
        warning.dismiss();

        if (clearedFields.length === 0) {
            return true;
        }

        warning.show(blockedMessage);

        return false;
    }, [clearedFields, warning, blockedMessage]);

    const captureRefusal = useCallback((error: unknown) => {
        const field = refusedFieldFrom(error);

        if (field === undefined) {
            return;
        }

        setRefusedFields((current) =>
            current.some((refused) => refused === field) ? current : [...current, field],
        );
    }, []);

    return useMemo(
        () => ({ isRequired, errorFor, confirmSaveAllowed, captureRefusal }),
        [isRequired, errorFor, confirmSaveAllowed, captureRefusal],
    );
}
