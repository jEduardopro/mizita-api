import { useTranslation } from 'react-i18next';
import { dateAndTime, elapsedSince } from './notification-dates';

type Props = {
    createdAt: string;
    timezone: string;
};

export function NotificationReceivedAgo({ createdAt, timezone }: Props) {
    const { t, i18n } = useTranslation('admin');
    const { unit, value } = elapsedSince(createdAt, Date.now());

    return (
        <time
            dateTime={createdAt}
            title={dateAndTime(createdAt, timezone, i18n.language)}
            className="relative z-10 shrink-0 pt-0.5 text-xs whitespace-nowrap text-muted-foreground tabular-nums"
        >
            <span aria-hidden="true">{t(`notifications.receivedAgo.${unit}`, { count: value })}</span>
            <span className="sr-only">{t(`notifications.receivedAgo.spoken.${unit}`, { count: value })}</span>
        </time>
    );
}
