import { TriangleAlert } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { IntegrationConnection } from '../types';
import { ConnectGoogleCalendarButton } from './ConnectGoogleCalendarButton';
import { ConnectionAccount } from './ConnectionAccount';
import { DisconnectGoogleCalendarDialog } from './DisconnectGoogleCalendarDialog';

const ACTION_BUTTON_CLASSES = 'h-11 w-full px-4 md:h-9';

function NotConnectedState() {
    const { t } = useTranslation('admin');

    return (
        <div className="grid gap-4">
            <div className="grid gap-1">
                <p className="text-sm font-medium">{t('integrations.googleCalendar.notConnected.title')}</p>
                <p className="text-sm text-pretty text-muted-foreground">
                    {t('integrations.googleCalendar.notConnected.body')}
                </p>
            </div>

            <ConnectGoogleCalendarButton label={t('integrations.googleCalendar.connect')} />
        </div>
    );
}

type ConnectedStateProps = {
    connection: IntegrationConnection;
    onDisconnect: () => void;
};

function ConnectedState({ connection, onDisconnect }: ConnectedStateProps) {
    const { t } = useTranslation('admin');

    return (
        <div className="grid gap-5">
            <ConnectionAccount accountEmail={connection.account_email} connectedAt={connection.connected_at} />

            <Button type="button" variant="outline" onClick={onDisconnect} className={ACTION_BUTTON_CLASSES}>
                {t('integrations.googleCalendar.disconnect')}
            </Button>
        </div>
    );
}

function NeedsReconnectState({ connection, onDisconnect }: ConnectedStateProps) {
    const { t } = useTranslation('admin');

    return (
        <div className="grid gap-5">
            <p
                role="note"
                className="grid grid-cols-[auto_minmax(0,1fr)] gap-2.5 rounded-lg bg-warning-surface px-3 py-3 text-sm text-pretty text-foreground"
            >
                <TriangleAlert aria-hidden="true" className="mt-0.5 size-4 text-warning" />
                <span>{t('integrations.googleCalendar.needsReconnect')}</span>
            </p>

            <ConnectionAccount accountEmail={connection.account_email} connectedAt={connection.connected_at} />

            <div className="grid gap-2">
                <ConnectGoogleCalendarButton label={t('integrations.googleCalendar.reconnect')} />

                <Button type="button" variant="ghost" onClick={onDisconnect} className={ACTION_BUTTON_CLASSES}>
                    {t('integrations.googleCalendar.disconnect')}
                </Button>
            </div>
        </div>
    );
}

type StateProps = {
    connection: IntegrationConnection | null;
    onDisconnect: () => void;
};

function ConnectionState({ connection, onDisconnect }: StateProps) {
    if (connection === null) {
        return <NotConnectedState />;
    }

    if (connection.status === 'needs_reconnect') {
        return <NeedsReconnectState connection={connection} onDisconnect={onDisconnect} />;
    }

    return <ConnectedState connection={connection} onDisconnect={onDisconnect} />;
}

type Props = {
    connection: IntegrationConnection | null;
    calendarName: string;
};

export function GoogleCalendarConnectionPanel({ connection, calendarName }: Props) {
    const { t } = useTranslation('admin');
    const headingId = useId();
    const [isDisconnectOpen, setDisconnectOpen] = useState(false);

    return (
        <section aria-labelledby={headingId} className="grid gap-4">
            <h3 id={headingId} className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {t('integrations.detail.connection')}
            </h3>

            <ConnectionState connection={connection} onDisconnect={() => setDisconnectOpen(true)} />

            <DisconnectGoogleCalendarDialog
                calendarName={calendarName}
                open={isDisconnectOpen}
                onOpenChange={setDisconnectOpen}
            />
        </section>
    );
}
