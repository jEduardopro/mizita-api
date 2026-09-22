import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { CUSTOMER_SHOW_TABS, customerShowTabFrom, type CustomerShowTab } from './use-customer-show-tab';

const TAB_LABEL_KEYS = {
    about: 'customers.show.tabs.about',
    appointments: 'customers.show.tabs.appointments',
    notes: 'customers.show.tabs.notes',
} as const satisfies Record<CustomerShowTab, string>;

type Props = {
    value: CustomerShowTab;
    onValueChange: (tab: CustomerShowTab) => void;
    about: ReactNode;
    appointments: ReactNode;
    notes: ReactNode;
};

export function CustomerShowTabs({ value, onValueChange, about, appointments, notes }: Props) {
    const { t } = useTranslation('admin');

    const panels: Record<CustomerShowTab, ReactNode> = { about, appointments, notes };

    return (
        <Tabs
            value={value}
            onValueChange={(next) => onValueChange(customerShowTabFrom(next))}
            className="gap-6"
        >
            <TabsList
                variant="line"
                className="grid w-full grid-cols-3 border-b border-border pb-[5px] group-data-horizontal/tabs:h-auto"
            >
                {CUSTOMER_SHOW_TABS.map((tab) => (
                    <TabsTrigger key={tab} value={tab} className="h-auto min-h-11 px-1">
                        {t(TAB_LABEL_KEYS[tab])}
                    </TabsTrigger>
                ))}
            </TabsList>

            {CUSTOMER_SHOW_TABS.map((tab) => (
                <TabsContent key={tab} value={tab}>
                    {panels[tab]}
                </TabsContent>
            ))}
        </Tabs>
    );
}
