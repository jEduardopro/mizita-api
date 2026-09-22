import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    APPOINTMENT_DETAILS_TABS,
    appointmentDetailsTabFrom,
    DEFAULT_APPOINTMENT_DETAILS_TAB,
    type AppointmentDetailsTab,
} from './appointment-details-tabs';

const TAB_LABEL_KEYS = {
    details: 'calendar.appointment.tabs.details',
    payments: 'calendar.appointment.tabs.payments',
} as const satisfies Record<AppointmentDetailsTab, string>;

type Props = {
    details: ReactNode;
    payments: ReactNode;
};

export function AppointmentDetailsTabs({ details, payments }: Props) {
    const { t } = useTranslation('admin');
    const [tab, setTab] = useState<AppointmentDetailsTab>(DEFAULT_APPOINTMENT_DETAILS_TAB);

    const panels: Record<AppointmentDetailsTab, ReactNode> = { details, payments };

    return (
        <Tabs
            value={tab}
            onValueChange={(next) => setTab(appointmentDetailsTabFrom(next))}
            className="min-h-0 flex-1 gap-0"
        >
            <TabsList
                variant="line"
                className="grid w-full shrink-0 grid-cols-2 border-b border-border px-4 pb-[5px] group-data-horizontal/tabs:h-auto"
            >
                {APPOINTMENT_DETAILS_TABS.map((candidate) => (
                    <TabsTrigger key={candidate} value={candidate} className="h-auto min-h-11 px-1">
                        {t(TAB_LABEL_KEYS[candidate])}
                    </TabsTrigger>
                ))}
            </TabsList>

            {APPOINTMENT_DETAILS_TABS.map((candidate) => (
                <TabsContent
                    key={candidate}
                    value={candidate}
                    className="overflow-y-auto overscroll-contain px-4 pt-4 pb-4"
                >
                    {panels[candidate]}
                </TabsContent>
            ))}
        </Tabs>
    );
}
