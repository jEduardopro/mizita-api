import { useCallback } from 'react';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { TAB_STATE_RESET } from './payment-history-parameters';

export const PAYMENT_HISTORY_TABS = ['sales', 'transactions'] as const;

export type PaymentHistoryTab = (typeof PAYMENT_HISTORY_TABS)[number];

type PaymentHistoryTabState = {
    tab: PaymentHistoryTab;
    setTab: (tab: PaymentHistoryTab) => void;
};

const DEFAULT_TAB: PaymentHistoryTab = 'sales';

const TAB_PARAMETER = 'tab';

export function paymentHistoryTabFrom(value: string | null): PaymentHistoryTab {
    return PAYMENT_HISTORY_TABS.find((candidate) => candidate === value) ?? DEFAULT_TAB;
}

export function usePaymentHistoryTab(): PaymentHistoryTabState {
    const { read, write } = useUrlQueryState();
    const tab = paymentHistoryTabFrom(read(TAB_PARAMETER));

    const setTab = useCallback(
        (next: PaymentHistoryTab) => {
            if (next === tab) {
                return;
            }

            write({ ...TAB_STATE_RESET, [TAB_PARAMETER]: next === DEFAULT_TAB ? null : next });
        },
        [tab, write],
    );

    return { tab, setTab };
}
