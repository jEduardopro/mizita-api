import { Link } from '@inertiajs/react';
import { ArrowRight, CirclePause } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { PlanUpgradeNotice } from '@/components/admin/PlanUpgradeNotice';
import { Button } from '@/components/ui/button';
import { PLAN_SETTINGS_HREF } from '@/lib/plan';
import type { IntegrationConnection } from '../types';
import { ConnectionAccount } from './ConnectionAccount';

const ACTION_BUTTON_CLASSES = 'h-11 w-full px-4 md:h-9';

const STACKED_NOTICE_CLASSES = 'md:flex-col md:items-stretch md:gap-1 md:[&>a]:self-start md:[&>a]:pl-6.5';

export function ConnectBlockedState() {
    const { t } = useTranslation('admin');

    return (
        <div className="grid gap-4">
            <div className="grid gap-1">
                <p className="text-sm font-medium">{t('integrations.googleCalendar.notConnected.title')}</p>
                <p className="text-sm text-pretty text-muted-foreground">
                    {t('integrations.googleCalendar.notConnected.body')}
                </p>
            </div>

            <PlanUpgradeNotice
                description={t('plan.integrations.connectBlocked')}
                className={STACKED_NOTICE_CLASSES}
            />
        </div>
    );
}

function SyncPausedNote() {
    const { t } = useTranslation('admin');
    const titleId = useId();

    return (
        <div
            role="note"
            aria-labelledby={titleId}
            className="grid grid-cols-[auto_minmax(0,1fr)] gap-2.5 rounded-lg border border-border bg-background px-3 py-3 text-sm"
        >
            <CirclePause aria-hidden="true" className="mt-0.5 size-4 text-muted-foreground" />

            <div className="grid gap-1">
                <p id={titleId} className="font-medium text-foreground">
                    {t('plan.integrations.syncPaused.title')}
                </p>

                <p className="leading-relaxed text-pretty text-muted-foreground">
                    {t('plan.integrations.syncPaused.description')}
                </p>

                <Link
                    href={PLAN_SETTINGS_HREF}
                    className="inline-flex min-h-11 items-center gap-1.5 justify-self-start rounded-md font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50 md:min-h-9"
                >
                    {t('plan.notice.link')}
                    <ArrowRight aria-hidden="true" className="size-3.5" />
                </Link>
            </div>
        </div>
    );
}

type SyncPausedStateProps = {
    connection: IntegrationConnection;
    onDisconnect: () => void;
};

export function SyncPausedState({ connection, onDisconnect }: SyncPausedStateProps) {
    const { t } = useTranslation('admin');

    return (
        <div className="grid gap-5">
            <SyncPausedNote />

            <ConnectionAccount accountEmail={connection.account_email} connectedAt={connection.connected_at} />

            <Button type="button" variant="outline" onClick={onDisconnect} className={ACTION_BUTTON_CLASSES}>
                {t('integrations.googleCalendar.disconnect')}
            </Button>
        </div>
    );
}
