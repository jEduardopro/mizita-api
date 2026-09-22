import { cn } from 'cn';
import { CalendarX2, CheckCircle2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { formatInstantTimeOfDay } from '@/lib/time';
import type { PublicBooking } from '../../types';
import { BookingReferenceCopy } from './BookingReferenceCopy';

type Props = {
    booking: PublicBooking;
    timezone: string;
    children?: ReactNode;
};

const DAY_FORMAT: Intl.DateTimeFormatOptions = {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
};

function dayLabel(instant: string, timezone: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, { ...DAY_FORMAT, timeZone: timezone }).format(
        new Date(instant),
    );
}

export function BookingConfirmationCard({ booking, timezone, children }: Props) {
    const { t, i18n } = useTranslation('public');

    const isCancelled = booking.status === 'cancelled';

    const details = [
        { id: 'service', label: t('booking.flow.summary.service'), value: booking.service_name },
        { id: 'staff', label: t('booking.flow.summary.staff'), value: booking.staff_member_name },
        {
            id: 'duration',
            label: t('booking.flow.summary.duration'),
            value: t('booking.services.duration', { count: booking.duration_minutes }),
        },
    ];

    return (
        <section className="grid gap-5 rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm sm:p-6">
            <p
                className={cn(
                    'flex items-center gap-1.5 justify-self-start rounded-full border px-3 py-1 text-xs font-medium',
                    isCancelled
                        ? 'border-destructive/30 bg-destructive/10 text-destructive'
                        : 'border-border bg-muted/60 text-muted-foreground',
                )}
            >
                {isCancelled ? (
                    <CalendarX2 aria-hidden="true" className="size-3.5" />
                ) : (
                    <CheckCircle2 aria-hidden="true" className="size-3.5" />
                )}

                {isCancelled ? t('booking.manage.status.cancelled') : t('booking.manage.status.booked')}
            </p>

            <div className="grid gap-1">
                <p className="font-heading text-[clamp(1.375rem,6vw,1.875rem)] leading-tight font-semibold tracking-[-0.02em] text-balance">
                    {dayLabel(booking.starts_at, timezone, i18n.language)}
                </p>

                <p className="font-heading text-[clamp(1.125rem,5vw,1.5rem)] leading-tight font-semibold tracking-[-0.01em]">
                    {formatInstantTimeOfDay(booking.starts_at, timezone)}
                </p>

                {booking.cancelled_at === null ? null : (
                    <p className="text-xs leading-relaxed text-destructive">
                        {t('booking.manage.cancelledAt', {
                            moment: t('booking.flow.summary.moment', {
                                day: dayLabel(booking.cancelled_at, timezone, i18n.language),
                                time: formatInstantTimeOfDay(booking.cancelled_at, timezone),
                            }),
                        })}
                    </p>
                )}
            </div>

            <dl className="grid gap-3 border-t border-dashed border-border pt-5">
                {details.map((detail) => (
                    <div key={detail.id} className="flex items-baseline justify-between gap-4">
                        <dt className="shrink-0 text-[0.6875rem] font-medium tracking-[0.1em] text-muted-foreground uppercase">
                            {detail.label}
                        </dt>

                        <dd className="min-w-0 text-right text-sm font-medium">{detail.value}</dd>
                    </div>
                ))}
            </dl>

            <BookingReferenceCopy code={booking.reference_code} />

            {children}
        </section>
    );
}
