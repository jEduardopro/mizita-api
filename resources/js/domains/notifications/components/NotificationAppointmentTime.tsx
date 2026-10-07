import { useTranslation } from 'react-i18next';
import { longDate, weekdayAndTime } from './notification-dates';

type Props = {
    startsAt: string;
    timezone: string;
};

export function NotificationAppointmentTime({ startsAt, timezone }: Props) {
    const { i18n } = useTranslation();

    return (
        <p className="truncate text-xs text-muted-foreground tabular-nums">
            <span className="sr-only">{`${longDate(startsAt, timezone, i18n.language)}, `}</span>
            <time dateTime={startsAt}>{weekdayAndTime(startsAt, timezone, i18n.language)}</time>
        </p>
    );
}
