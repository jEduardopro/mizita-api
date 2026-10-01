import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    PAYMENT_HISTORY_TABS,
    paymentHistoryTabFrom,
    type PaymentHistoryTab,
} from './use-payment-history-tab';

const TAB_LABEL_KEYS = {
    sales: 'payments.history.tabs.sales',
    transactions: 'payments.history.tabs.transactions',
} as const satisfies Record<PaymentHistoryTab, string>;

type Props = {
    value: PaymentHistoryTab;
    onValueChange: (tab: PaymentHistoryTab) => void;
    sales: ReactNode;
    transactions: ReactNode;
};

export function PaymentHistoryTabs({ value, onValueChange, sales, transactions }: Props) {
    const { t } = useTranslation('admin');

    const panels: Record<PaymentHistoryTab, ReactNode> = { sales, transactions };

    return (
        <Tabs
            value={value}
            onValueChange={(next) => onValueChange(paymentHistoryTabFrom(next))}
            className="min-h-0 flex-1 gap-6"
        >
            <TabsList
                variant="line"
                className="grid w-full shrink-0 grid-cols-2 justify-start border-b border-border pb-[5px] group-data-horizontal/tabs:h-auto sm:flex"
            >
                {PAYMENT_HISTORY_TABS.map((tab) => (
                    <TabsTrigger key={tab} value={tab} className="h-auto min-h-11 px-1 sm:flex-none sm:px-3 md:min-h-9">
                        {t(TAB_LABEL_KEYS[tab])}
                    </TabsTrigger>
                ))}
            </TabsList>

            {PAYMENT_HISTORY_TABS.map((tab) => (
                <TabsContent key={tab} value={tab} className="flex min-h-0 flex-col">
                    {panels[tab]}
                </TabsContent>
            ))}
        </Tabs>
    );
}
