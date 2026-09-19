import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';

export type CreatedCustomer = {
    id: string;
    name: string;
};

type CustomerCreationRequest = {
    suggestedName: string;
    onCreated: (customer: CreatedCustomer) => void;
};

type CustomerCreationValue = {
    request: CustomerCreationRequest | null;
    requestCreate: (suggestedName: string, onCreated: (customer: CreatedCustomer) => void) => void;
    dismiss: () => void;
};

const CustomerCreationContext = createContext<CustomerCreationValue | null>(null);

function useCustomerCreationContext(): CustomerCreationValue {
    const value = useContext(CustomerCreationContext);

    if (value === null) {
        throw new Error('Customer creation requires a CustomerCreationProvider ancestor.');
    }

    return value;
}

type Props = {
    children: ReactNode;
};

export function CustomerCreationProvider({ children }: Props) {
    const [request, setRequest] = useState<CustomerCreationRequest | null>(null);

    const requestCreate = useCallback(
        (suggestedName: string, onCreated: (customer: CreatedCustomer) => void) => {
            setRequest({ suggestedName, onCreated });
        },
        [],
    );

    const dismiss = useCallback(() => setRequest(null), []);

    const value = useMemo(
        () => ({ request, requestCreate, dismiss }),
        [request, requestCreate, dismiss],
    );

    return (
        <CustomerCreationContext.Provider value={value}>{children}</CustomerCreationContext.Provider>
    );
}

export function useCustomerCreation(): Pick<CustomerCreationValue, 'requestCreate'> {
    const { requestCreate } = useCustomerCreationContext();

    return { requestCreate };
}

export function usePendingCustomerCreation(): Pick<CustomerCreationValue, 'request' | 'dismiss'> {
    const { request, dismiss } = useCustomerCreationContext();

    return { request, dismiss };
}
