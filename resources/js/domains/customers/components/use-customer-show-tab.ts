import { useCallback } from 'react';
import { useUrlQueryState } from '@/hooks/use-url-query-state';

export const CUSTOMER_SHOW_TABS = ['about', 'notes', 'appointments'] as const;

export type CustomerShowTab = (typeof CUSTOMER_SHOW_TABS)[number];

export type CustomerShowTabState = {
    tab: CustomerShowTab;
    setTab: (tab: CustomerShowTab) => void;
};

const DEFAULT_TAB: CustomerShowTab = 'about';

const TAB_PARAMETER = 'tab';

export function customerShowTabFrom(value: string | null): CustomerShowTab {
    return CUSTOMER_SHOW_TABS.find((candidate) => candidate === value) ?? DEFAULT_TAB;
}

export function useCustomerShowTab(): CustomerShowTabState {
    const url = useUrlQueryState();
    const { write } = url;

    const setTab = useCallback(
        (tab: CustomerShowTab) => write({ [TAB_PARAMETER]: tab === DEFAULT_TAB ? null : tab }),
        [write],
    );

    return { tab: customerShowTabFrom(url.read(TAB_PARAMETER)), setTab };
}
