import { useTranslation } from 'react-i18next';
import { formatInstantTimeOfDay } from '@/lib/time';
import { AppointmentStaffBadge } from './AppointmentStaffBadge';
import { ServiceColorDot } from './ServiceColorDot';
import type { Appointment } from '../types';

type Props = {
    appointment: Appointment;
    timezone: string;
    onSelect: (appointment: Appointment) => void;
};

export function AppointmentTimelineRow({ appointment, timezone, onSelect }: Props) {
    const { t } = useTranslation('admin');

    const startsAt = formatInstantTimeOfDay(appointment.starts_at, timezone);
    const endsAt = formatInstantTimeOfDay(appointment.ends_at, timezone);

    return (
        <li className="min-w-0">
            <button
                type="button"
                onClick={() => onSelect(appointment)}
                aria-label={t('customers.show.appointments.open', {
                    service: appointment.service.name,
                    time: startsAt,
                })}
                className="grid min-h-11 w-full gap-1 rounded-lg px-2 py-2 text-left transition-colors outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 sm:grid-cols-[auto_1fr_auto] sm:items-center sm:gap-4"
            >
                <span className="text-sm text-muted-foreground tabular-nums">
                    {startsAt} – {endsAt}
                </span>

                <span className="flex min-w-0 items-center gap-2">
                    <ServiceColorDot color={appointment.service.color} />

                    <span className="min-w-0 truncate text-sm font-medium">
                        {appointment.customer.name}
                    </span>

                    <span className="min-w-0 truncate text-sm text-muted-foreground">
                        {appointment.service.name}
                    </span>
                </span>

                <AppointmentStaffBadge name={appointment.staff_member.name} />
            </button>
        </li>
    );
}
