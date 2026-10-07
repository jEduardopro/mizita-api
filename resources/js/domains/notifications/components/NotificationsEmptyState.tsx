import { BellRing, CheckCheck, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { NotificationStatus } from '../types';

type EmptyStateCopy = {
    icon: LucideIcon;
    title: 'notifications.empty.unread.title' | 'notifications.empty.all.title';
    body: 'notifications.empty.unread.body' | 'notifications.empty.all.body';
};

const EMPTY_STATES: Record<NotificationStatus, EmptyStateCopy> = {
    unread: {
        icon: CheckCheck,
        title: 'notifications.empty.unread.title',
        body: 'notifications.empty.unread.body',
    },
    all: {
        icon: BellRing,
        title: 'notifications.empty.all.title',
        body: 'notifications.empty.all.body',
    },
};

type Props = {
    status: NotificationStatus;
};

export function NotificationsEmptyState({ status }: Props) {
    const { t } = useTranslation('admin');
    const { icon: Icon, title, body } = EMPTY_STATES[status];

    return (
        <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
            <Icon aria-hidden="true" className="size-6 text-muted-foreground" />

            <p className="font-medium">{t(title)}</p>

            <p className="max-w-sm text-sm text-pretty text-muted-foreground">{t(body)}</p>
        </div>
    );
}
