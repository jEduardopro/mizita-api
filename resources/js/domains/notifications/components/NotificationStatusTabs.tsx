import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { NOTIFICATION_STATUSES, type NotificationStatus } from '../types';
import { notificationStatusFrom } from './use-notification-filters';

const TAB_LABEL_KEYS = {
    unread: 'notifications.tabs.unread',
    all: 'notifications.tabs.all',
} as const satisfies Record<NotificationStatus, string>;

type Props = {
    value: NotificationStatus;
    onValueChange: (status: NotificationStatus) => void;
    filter?: ReactNode;
    children: ReactNode;
};

export function NotificationStatusTabs({ value, onValueChange, filter, children }: Props) {
    const { t } = useTranslation('admin');

    return (
        <Tabs
            value={value}
            onValueChange={(next) => onValueChange(notificationStatusFrom(next))}
            className="gap-6"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between sm:border-b sm:border-border">
                <TabsList
                    variant="line"
                    className="grid w-full grid-cols-2 justify-start border-b border-border pb-[5px] group-data-horizontal/tabs:h-auto sm:flex sm:w-auto sm:border-b-0"
                >
                    {NOTIFICATION_STATUSES.map((status) => (
                        <TabsTrigger
                            key={status}
                            value={status}
                            className="h-auto min-h-11 px-1 sm:flex-none sm:px-3 md:min-h-9"
                        >
                            {t(TAB_LABEL_KEYS[status])}
                        </TabsTrigger>
                    ))}
                </TabsList>

                {filter === undefined ? null : <div className="sm:pb-2">{filter}</div>}
            </div>

            {NOTIFICATION_STATUSES.map((status) => (
                <TabsContent key={status} value={status}>
                    {children}
                </TabsContent>
            ))}
        </Tabs>
    );
}
