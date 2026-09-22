import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { AppointmentTimelineRow } from './AppointmentTimelineRow';
import type { AppointmentDayGroup } from './appointment-timeline-groups';
import type { Appointment } from '../types';

const UTC_TIMEZONE = 'UTC';

const TODAY_BADGE = 'inline-flex size-7 items-center justify-center rounded-full bg-foreground text-background';

type Props = {
    group: AppointmentDayGroup;
    timezone: string;
    onSelect: (appointment: Appointment) => void;
};

type CivilDayLabels = {
    day: string;
    month: string;
    weekday: string;
};

function civilDayLabels(date: string, locale: string): CivilDayLabels {
    const parts = new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'short',
        weekday: 'short',
        timeZone: UTC_TIMEZONE,
    }).formatToParts(new Date(`${date}T00:00:00Z`));

    const partOf = (type: string) => parts.find((part) => part.type === type)?.value ?? '';

    return { day: partOf('day'), month: partOf('month'), weekday: partOf('weekday') };
}

export function AppointmentTimelineDay({ group, timezone, onSelect }: Props) {
    const { t, i18n } = useTranslation('admin');

    const labels = civilDayLabels(group.date, i18n.language);

    return (
        <li className="col-span-2 grid min-w-0 grid-cols-subgrid">
            <p className="pt-2 text-[0.6875rem] leading-tight text-muted-foreground">
                <span
                    className={cn(
                        'block text-base font-semibold text-foreground tabular-nums',
                        group.isToday && TODAY_BADGE,
                    )}
                >
                    {labels.day}
                </span>

                <span className="mt-1 block capitalize">{`${labels.month}, ${labels.weekday}`}</span>
            </p>

            {group.appointments.length === 0 ? (
                <p className="flex min-h-11 min-w-0 items-center px-2 text-sm text-muted-foreground">
                    {t('customers.show.appointments.today')}
                </p>
            ) : (
                <ol className="grid min-w-0 gap-1">
                    {group.appointments.map((appointment) => (
                        <AppointmentTimelineRow
                            key={appointment.id}
                            appointment={appointment}
                            timezone={timezone}
                            onSelect={onSelect}
                        />
                    ))}
                </ol>
            )}
        </li>
    );
}
