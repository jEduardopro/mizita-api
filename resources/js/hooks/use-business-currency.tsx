import { createContext, useContext, type ReactNode } from 'react';

const BusinessCurrencyContext = createContext<string | null>(null);

type Props = {
    currencyCode: string;
    children: ReactNode;
};

export function BusinessCurrencyProvider({ currencyCode, children }: Props) {
    return (
        <BusinessCurrencyContext.Provider value={currencyCode}>{children}</BusinessCurrencyContext.Provider>
    );
}

export function useBusinessCurrency(): string {
    const currencyCode = useContext(BusinessCurrencyContext);

    if (currencyCode === null) {
        throw new Error('Business currency requires a BusinessCurrencyProvider ancestor.');
    }

    return currencyCode;
}
